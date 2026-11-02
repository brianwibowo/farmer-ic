<?php

namespace App\Services;

use App\Models\ActivityType;
use App\Models\CropVariety;
use App\Models\SopTemplate;
use App\Models\SopTemplateTask;
use App\Models\SopTemplateVersion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SopTemplateService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SopTemplate>
     */
    public function paginate(User $actor, Team $team, array $filters): LengthAwarePaginator
    {
        $query = SopTemplate::query()
            ->visibleTo($actor, $team)
            ->with(['team:id,name', 'variety:id,commodity_id,name', 'variety.commodity:id,name'])
            ->withCount('versions');

        if (($filters['search'] ?? '') !== '') {
            $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $filters['search'])).'%';
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'ilike', $search)
                    ->orWhereHas('variety', fn ($variety) => $variety->where('name', 'ilike', $search));
            });
        }
        if (($filters['variety_id'] ?? '') !== '') {
            $query->where('variety_id', $filters['variety_id']);
        }
        if (($filters['is_active'] ?? '') !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $sort = in_array(($filters['sort'] ?? ''), ['name', 'created_at'], true) ? $filters['sort'] : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction)->orderBy('id')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function detail(SopTemplate $template): SopTemplate
    {
        return $template->load([
            'team:id,name',
            'variety:id,commodity_id,name',
            'variety.commodity:id,name',
            'versions' => fn ($query) => $query->orderByDesc('version')->with([
                'creator:id,name',
                'publisher:id,name',
                'tasks.activityType:id,code,name',
            ]),
        ]);
    }

    /** @return array<int, array{id: string, name: string, commodity: string}> */
    public function varieties(): array
    {
        return CropVariety::query()
            ->with('commodity:id,name')
            ->orderBy('name')
            ->get(['id', 'commodity_id', 'name'])
            ->map(fn (CropVariety $variety): array => [
                'id' => $variety->id,
                'name' => $variety->name,
                'commodity' => $variety->commodity?->name ?? '',
            ])
            ->all();
    }

    /** @return array<int, array{id: string, code: string, name: string}> */
    public function activityTypes(): array
    {
        return ActivityType::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (ActivityType $type): array => ['id' => $type->id, 'code' => $type->code, 'name' => $type->name])
            ->all();
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, Team $team, array $data): SopTemplate
    {
        return DB::transaction(function () use ($actor, $team, $data): SopTemplate {
            $template = SopTemplate::query()->create([
                'team_id' => $team->id,
                'variety_id' => $data['variety_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $actor->id,
            ]);

            $template->versions()->create([
                'version' => 1,
                'notes' => 'Versi awal.',
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);

            return $this->detail($template);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(SopTemplate $template, array $data): SopTemplate
    {
        $template->fill(Arr::only($data, ['name', 'description', 'is_active']))->save();

        return $this->detail($template);
    }

    public function delete(SopTemplate $template): void
    {
        DB::transaction(function () use ($template): void {
            $versionIds = $template->versions()->pluck('id')->all();

            if ($versionIds !== []) {
                SopTemplateTask::query()->whereIn('sop_template_version_id', $versionIds)->delete();
                SopTemplateVersion::query()->whereIn('id', $versionIds)->delete();
            }

            $template->delete();
        });
    }

    public function storeVersion(SopTemplate $template, User $actor, ?string $notes = null): SopTemplateVersion
    {
        return DB::transaction(function () use ($template, $actor, $notes): SopTemplateVersion {
            $latest = $template->versions()->orderByDesc('version')->first();

            if ($latest instanceof SopTemplateVersion && $latest->status === 'draft') {
                throw ValidationException::withMessages([
                    'version' => 'Masih ada versi draft. Selesaikan atau publikasikan versi tersebut sebelum membuat versi baru.',
                ]);
            }

            $version = $template->versions()->create([
                'version' => ($latest?->version ?? 0) + 1,
                'notes' => $notes ?? 'Revisi dari versi sebelumnya.',
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);

            if ($latest instanceof SopTemplateVersion) {
                $latest->tasks()->each(function (SopTemplateTask $task) use ($version): void {
                    $version->tasks()->create([
                        ...$this->taskAttributes($task),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
            }

            return $version->load(['tasks.activityType:id,code,name']);
        });
    }

    public function publish(SopTemplateVersion $version, User $actor): SopTemplateVersion
    {
        if ($version->status !== 'draft') {
            throw ValidationException::withMessages(['version' => 'Hanya versi draft yang dapat dipublikasikan.']);
        }
        if ($version->tasks()->count() === 0) {
            throw ValidationException::withMessages(['version' => 'Tambahkan minimal satu langkah sebelum mempublikasikan SOP.']);
        }

        return DB::transaction(function () use ($version, $actor): SopTemplateVersion {
            SopTemplateVersion::query()
                ->where('sop_template_id', $version->sop_template_id)
                ->whereKeyNot($version->id)
                ->where('status', 'published')
                ->update(['status' => 'retired', 'updated_at' => now()]);

            $version->forceFill([
                'status' => 'published',
                'published_by' => $actor->id,
                'published_at' => now(),
            ])->save();

            return $version->load(['tasks.activityType:id,code,name', 'publisher:id,name']);
        });
    }

    /** @param array<string, mixed> $data */
    public function createTask(SopTemplateVersion $version, array $data): SopTemplateTask
    {
        $this->assertDraft($version);

        return $version->tasks()->create($this->taskAttributes($data));
    }

    /** @param array<string, mixed> $data */
    public function updateTask(SopTemplateTask $task, array $data): SopTemplateTask
    {
        $this->assertDraft($task->version);

        $task->fill($this->taskAttributes($data))->save();

        return $task;
    }

    public function deleteTask(SopTemplateTask $task): void
    {
        $this->assertDraft($task->version);
        $task->delete();
    }

    private function assertDraft(SopTemplateVersion $version): void
    {
        if ($version->status !== 'draft') {
            throw ValidationException::withMessages(['version' => 'Versi yang sudah dipublikasikan tidak dapat diubah.']);
        }
    }

    /** @param array<string, mixed>|SopTemplateTask $source
     * @return array<string, mixed>
     */
    private function taskAttributes(array|SopTemplateTask $source): array
    {
        $data = $source instanceof SopTemplateTask ? $source->getAttributes() : $source;

        return [
            'activity_type_id' => $data['activity_type_id'],
            'stage_code' => $data['stage_code'],
            'name' => $data['name'],
            'instructions' => $data['instructions'] ?? null,
            'offset_days' => (int) ($data['offset_days'] ?? 0),
            'tolerance_days' => (int) ($data['tolerance_days'] ?? 0),
            'is_required' => (bool) ($data['is_required'] ?? true),
        ];
    }
}
