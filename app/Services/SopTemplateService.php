<?php

namespace App\Services;

use App\Models\Commodity;
use App\Models\Farm;
use App\Models\SopTemplate;
use App\Models\SopTemplateTask;
use App\Models\SopTemplateVersion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
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
            ->with(['team:id,name', 'commodity:id,name'])
            ->withCount('versions');

        if (($filters['search'] ?? '') !== '') {
            $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $filters['search'])).'%';
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'ilike', $search)
                    ->orWhereHas('commodity', fn ($commodity) => $commodity->where('name', 'ilike', $search));
            });
        }
        if (($filters['commodity_id'] ?? '') !== '') {
            $query->where('commodity_id', $filters['commodity_id']);
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
            'commodity:id,name',
            'versions' => fn ($query) => $query->orderByDesc('version')->with([
                'creator:id,name',
                'publisher:id,name',
                'tasks',
            ]),
        ]);
    }

    /** @return array<int, array{id: string, name: string}> */
    public function commodities(): array
    {
        return Commodity::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Commodity $commodity): array => [
                'id' => $commodity->id,
                'name' => $commodity->name,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function options(User $actor, Team $team): array
    {
        return SopTemplate::query()
            ->visibleTo($actor, $team)
            ->with('commodity:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'commodity_id', 'is_active'])
            ->map(fn (SopTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'is_active' => $template->is_active,
                'commodity' => $template->commodity?->only(['id', 'name']),
            ])
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function defaultForFarm(Farm $farm): ?array
    {
        if ($farm->default_sop_template_id === null) {
            return null;
        }

        $team = $farm->team;
        if (! $team instanceof Team) {
            return null;
        }

        $template = SopTemplate::query()
            ->where('team_id', $farm->team_id)
            ->with('commodity:id,name')
            ->find($farm->default_sop_template_id);

        if (! $template instanceof SopTemplate) {
            return null;
        }

        $version = $template->versions()
            ->where('status', 'published')
            ->orderByDesc('version')
            ->first();
        $tasksCount = $version === null ? 0 : $version->tasks()->count();

        return [
            'id' => $template->id,
            'name' => $template->name,
            'is_active' => $template->is_active,
            'commodity' => $template->commodity?->only(['id', 'name']),
            'published_version' => $version?->only(['id', 'version', 'published_at']),
            'tasks_count' => $tasksCount,
            'is_usable' => $template->is_active && $version !== null && $tasksCount > 0,
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, Team $team, array $data): SopTemplate
    {
        return DB::transaction(function () use ($actor, $team, $data): SopTemplate {
            $template = SopTemplate::query()->create([
                'team_id' => $team->id,
                'commodity_id' => $data['commodity_id'],
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
                'version' => $latest instanceof SopTemplateVersion ? $latest->version + 1 : 1,
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

            return $version->load(['tasks']);
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

            return $version->load(['tasks', 'publisher:id,name']);
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
            'activity_category' => $data['activity_category'],
            'activity_subcategory' => $data['activity_subcategory'] ?? null,
            'name' => $data['name'],
            'instructions' => $data['instructions'] ?? null,
            'offset_days' => (int) ($data['offset_days'] ?? 0),
            'tolerance_days' => (int) ($data['tolerance_days'] ?? 0),
            'is_required' => (bool) ($data['is_required'] ?? true),
        ];
    }
}
