# Farmer IC RAG: keputusan teknis dan rencana implementasi

Dokumen ini menetapkan keputusan awal untuk membangun Retrieval-Augmented Generation (RAG) pada Farmer IC. RAG dipakai untuk jalur knowledge dan panduan, bukan untuk semua jenis pertanyaan.

Tanggal keputusan: 19 Oktober 2026

## Keputusan singkat

Farmer IC dapat memakai Laravel sebagai orchestrator RAG. Aplikasi tidak perlu ditulis ulang ke Python atau framework agent lain.

Stack yang direkomendasikan untuk MVP production di satu VPS:

```text
Laravel API + Inertia/Vue
PostgreSQL          system of record
Redis               queue, cache, rate limit
Qdrant              vector and hybrid retrieval index
OpenRouter/API      LLM, embedding, STT, image generation
OpenTelemetry       trace conventions
Langfuse atau Phoenix observability/evaluation
```

Qdrant dipilih sebagai index retrieval karena mendukung semantic search, metadata filtering, hybrid dense+sparse search, dan multi-stage query. PostgreSQL tetap menjadi sumber kebenaran dokumen, team, user, conversation, dan audit. Qdrant dapat dibangun ulang dari PostgreSQL apabila index rusak atau konfigurasi embedding berubah.

Untuk MVP, Laravel tidak perlu memakai LangChain, LlamaIndex, atau Haystack sebagai dependency utama. Workflow inti dibuat sebagai service Laravel yang kecil dan dapat diuji. Framework tambahan dapat dipakai sebagai sidecar Python jika kebutuhan retrieval atau multimodal kelak melewati kemampuan implementasi PHP yang sederhana.

## Mengapa memakai RAG

LLM menyimpan pengetahuan di parameter model, sedangkan Farmer IC membutuhkan jawaban yang bersumber dari:

- data aplikasi yang berubah;
- knowledge pertanian yang diterbitkan dan direview;
- dokumen per team;
- gambar atau angka yang dikirim user;
- batasan data yang memang belum tersedia.

RAG memberi jalur untuk mengambil evidence yang relevan sebelum LLM menyusun jawaban. Ini membantu mengurangi jawaban yang terdengar yakin tetapi tidak memiliki dasar. RAG tidak menjamin kebenaran; retrieval yang salah tetap dapat menghasilkan jawaban yang salah. Karena itu RAG harus digabungkan dengan threshold, citation, abstention, validator, dan evaluasi.

RAG hanya cocok untuk knowledge. Pembagian jalurnya:

| Permintaan | Jalur | Sumber kebenaran |
| --- | --- | --- |
| Kondisi kebun, aktivitas, transaksi | Data service | query data aplikasi |
| Cara menanam, pemupukan, hama | Knowledge RAG + LLM | chunk knowledge yang direview |
| Finansial yang belum tersedia | Knowledge/abstention | availability guardrail |
| Foto tanaman | Vision + evidence | gambar user dan knowledge terkait |
| Buat infografis | Retrieval → visual spec → image generation | fakta yang sudah divalidasi |
| Di luar domain | Fallback | policy aplikasi |

Jangan menjadikan RAG sebagai pengganti data service. Data angka, status, dan transaksi harus diambil secara deterministik agar LLM tidak mengarang nilai.

## Apakah pilihan ini cocok untuk Farmer IC

### Kecocokan Laravel

Laravel cocok sebagai lapisan orchestrator karena sudah memiliki:

- authentication dan team membership;
- route/controller untuk percakapan;
- model `AiConversation`, `AiMessage`, `AiAttachment`, `AiUsageEvent`, dan `AiKnowledgeDocument`;
- policy publikasi dokumen knowledge;
- queue abstraction;
- HTTP client untuk memanggil embedding provider dan Qdrant;
- migration, validation, logging, dan testing.

Laravel tidak perlu mengerjakan operasi vector search di PHP. Laravel mengirim query ke Qdrant, memfilter `team_id`, menerima chunk teratas, lalu menyusun context untuk LLM.

### Kecocokan Docker dan VPS

Docker di repository saat ini menghasilkan satu runtime Apache. Untuk production RAG, topology tersebut perlu diperluas menjadi service web, worker, PostgreSQL, Redis, dan Qdrant. Semua service dapat berjalan pada satu VPS pada tahap awal, selama resource dan backup direncanakan.

LLM, embedding, STT, dan image generation tetap dipanggil melalui API server-side. VPS tidak memerlukan GPU untuk MVP jika inference tidak dijalankan secara lokal.

### Qdrant dibandingkan PostgreSQL + pgvector

| Pilihan | Keunggulan | Biaya/risiko |
| --- | --- | --- |
| PostgreSQL + pgvector | Lebih sedikit service, metadata dan vector berada di satu database | Setup extension, tuning vector, dan hybrid search lebih menjadi tanggung jawab aplikasi |
| PostgreSQL + Qdrant | API retrieval, filtering, hybrid search, dan multi-stage query lebih jelas | Memiliki service, volume, backup, dan health check tambahan |

Rekomendasi awal: gunakan PostgreSQL sebagai system of record dan Qdrant sebagai index retrieval. Jika operasional satu VPS menjadi terlalu berat, Qdrant dapat diganti dengan pgvector melalui adapter `Retriever` tanpa mengubah jalur percakapan.

## Bukti dan penelitian terdahulu

Pilihan ini bukan asumsi internal semata.

1. Survey RAG conversational terbaru membagi sistem menjadi document segmentation, embedding/indexing, retrieval, reranking, grounding, attribution, dan evaluation. Survey tersebut juga menyoroti retrieval robustness, verifiable generation, privacy, dan multimodal grounding sebagai masalah utama. [Survey conversational RAG](https://link.springer.com/article/10.1007/s44163-026-02373-y)

2. OpenAI Knowledge Retrieval adalah starter kit open source yang memisahkan ingestion, retrieval, query expansion, filtering, reranking, response assembly, dan evals. Struktur ini dekat dengan kebutuhan Farmer IC. [OpenAI Knowledge Retrieval](https://github.com/openai/openai-knowledge-retrieval)

3. Haystack menyediakan contoh pipeline dengan embedder, retriever, prompt builder, generator, answer builder, dan evaluator untuk MRR, recall, faithfulness, semantic similarity, dan contextual relevance. [Haystack evaluation pipeline](https://github.com/deepset-ai/haystack/blob/main/e2e/pipelines/test_evaluation_pipeline.py)

4. Qdrant mendokumentasikan hybrid search yang menggabungkan semantic dan lexical representation serta filtering berdasarkan payload. Ini relevan untuk query Farmer IC yang kadang memakai bahasa natural dan kadang memakai istilah persis, angka, fase, atau kode komoditas. [Qdrant hybrid queries](https://qdrant.tech/documentation/search/hybrid-queries/) [Qdrant filtering](https://qdrant.tech/documentation/search-patterns/vector-search-filtering/)

5. Rasa memakai confidence threshold dan ambiguity threshold untuk menghindari pemaksaan intent ketika classifier tidak yakin. Prinsip ini dipakai di Farmer IC walaupun Rasa tidak perlu menjadi dependency. [Rasa FallbackClassifier](https://github.com/RasaHQ/rasa/blob/3.6.x/docs/docs/components.mdx)

6. Langfuse dan Phoenix menunjukkan pola observability untuk LLM: trace harus mencatat model, prompt, output, retrieval, tool, token, latency, dan evaluasi. OpenTelemetry menyediakan semantic conventions agar telemetry tidak vendor-specific. [Langfuse observability](https://github.com/langfuse/langfuse-docs/blob/main/content/docs/observability/overview.mdx) [Phoenix](https://github.com/Arize-ai/phoenix) [OpenTelemetry GenAI observability](https://opentelemetry.io/blog/2026/genai-observability/)

7. DeepEval memisahkan answer relevancy, faithfulness, contextual relevancy, contextual recall, dan hallucination. Satu skor “jawaban bagus” tidak cukup untuk menemukan apakah masalahnya berasal dari retrieval atau generation. [DeepEval RAG Triad](https://github.com/confident-ai/deepeval/blob/main/docs/content/guides/guides-rag-triad.mdx)

Referensi tersebut tidak berarti Farmer IC harus menginstal semua framework. Yang diambil adalah pola sistem dan metrik yang sudah dipakai oleh proyek nyata.

## Kontrak arsitektur

### Knowledge document

Dokumen canonical disimpan di PostgreSQL melalui `ai_knowledge_documents`. Dokumen harus memiliki:

- `team_id`;
- `title`;
- `content`;
- `source`;
- `status` (`draft`, `published`, `archived`);
- `version`;
- `content_hash`;
- `published_at`.

Dokumen berstatus `draft` tidak boleh masuk retrieval production. Publish menjadi event yang menjadwalkan indexing.

### Chunk

Tambahkan tabel `ai_knowledge_chunks`:

```text
id
ai_knowledge_document_id
team_id
chunk_index
content
content_hash
version
token_count
qdrant_point_id
metadata
indexed_at
status
```

Chunk harus idempotent. Re-index dokumen yang sama tidak boleh membuat duplikat. Jika `content_hash` tidak berubah, indexing dapat dilewati.

Payload Qdrant minimal:

```json
{
  "team_id": 12,
  "document_id": 91,
  "chunk_id": 342,
  "version": 3,
  "status": "published",
  "topic": "kedelai",
  "source": "Panduan budidaya kedelai",
  "content_hash": "..."
}
```

Filter `team_id` wajib diterapkan sebelum hasil diberikan ke LLM. Retrieval tidak boleh mengambil chunk team lain lalu membuangnya setelah context sudah terbentuk.

### Retrieval result

Retriever tidak langsung mengembalikan string. Ia mengembalikan evidence terstruktur:

```json
{
  "chunk_id": 342,
  "document_id": 91,
  "content": "...",
  "source": "Panduan budidaya kedelai",
  "score": 0.84,
  "retrieval_method": "hybrid",
  "metadata": {
    "team_id": 12,
    "topic": "kedelai"
  }
}
```

Jika score atau jumlah evidence berada di bawah threshold, sistem harus abstain atau meminta konteks tambahan.

### Grounded response

Generator menerima hanya evidence yang sudah lolos filter. Bentuk internal yang disarankan:

```json
{
  "answer": "...",
  "citations": [
    {
      "chunk_id": 342,
      "source": "Panduan budidaya kedelai"
    }
  ],
  "claims": [
    {
      "text": "...",
      "evidence_chunk_ids": [342]
    }
  ],
  "should_abstain": false,
  "follow_up_question": null
}
```

Frontend dapat menampilkan jawaban yang ramah, tetapi server menyimpan response terstruktur dan citation untuk audit.

## Rencana pre: sebelum membuat RAG

### 1. Bekukan batas produk

MVP v1 hanya mencakup:

- panduan komoditas;
- pemupukan dan irigasi;
- hama/penyakit sebagai panduan umum;
- penjelasan screen Farmer IC;
- knowledge dokumen per team;
- citation dan abstention.

Data dashboard, financial statement, real-time weather, diagnosis definitif, dan operational action harus memiliki status availability yang eksplisit.

### 2. Buat golden dataset

Sebelum memilih embedding atau tuning prompt, siapkan minimal 50 kasus:

- variasi pendek dan panjang;
- sinonim dan bahasa sehari-hari;
- typo dan voice transcript;
- pertanyaan lanjutan;
- pertanyaan multi-intent;
- pertanyaan dengan komoditas berbeda;
- pertanyaan tanpa evidence;
- prompt injection pada dokumen dan input user;
- pertanyaan yang seharusnya masuk data service, bukan RAG.

Setiap kasus memiliki expected route, expected evidence, expected abstention, dan rubric jawaban.

### 3. Tetapkan metrik sebelum implementasi

Target awal yang harus disepakati:

| Metrik | Tujuan |
| --- | --- |
| Intent accuracy | Router memilih jalur yang benar |
| Fallback precision | Sistem hanya meminta klarifikasi saat perlu |
| Recall@K / MRR | Evidence relevan muncul di hasil teratas |
| Contextual relevance | Chunk yang diambil benar-benar relevan |
| Faithfulness | Klaim jawaban didukung evidence |
| Citation coverage | Klaim faktual memiliki citation |
| Abstention accuracy | Sistem menolak saat evidence tidak cukup |
| p50/p95 latency | Pengalaman user dan kapasitas VPS |
| Error rate | Kegagalan provider, queue, retrieval, dan validation |

Confidence internal harus dikalibrasi terhadap dataset. Jangan menampilkan angka confidence mentah kepada user sebelum terbukti bermakna.

### 4. Audit keamanan dan data

Sebelum indexing:

- hapus API key, password, token, dan data pribadi dari dokumen;
- definisikan retention trace;
- filter retrieval dengan `team_id`;
- jangan menyimpan attachment mentah di telemetry;
- pastikan prompt injection di dokumen dianggap data, bukan instruksi system;
- siapkan backup PostgreSQL dan volume Qdrant.

## Rencana process: vertical slice dalam satu hari

Satu hari cukup untuk membuktikan RAG end-to-end pada satu domain. Satu hari tidak cukup untuk menyatakan sistem production-ready.

### Jam 1: kontrak dan smoke test

- finalisasi schema chunk dan response;
- pilih satu domain, misalnya kedelai;
- siapkan 10–20 dokumen/chunk valid;
- jalankan Qdrant melalui Docker;
- verifikasi filter `team_id`.

### Jam 2: indexing

- buat `KnowledgeIndexer`;
- normalize dan chunk dokumen;
- hitung hash dan token count;
- panggil embedding provider;
- upsert vector dan payload ke Qdrant;
- simpan `qdrant_point_id` dan index version.

### Jam 3: retrieval

- buat `KnowledgeRetriever`;
- query embedding;
- hybrid search atau dense search awal;
- filter `team_id` dan `status=published`;
- kembalikan top-k evidence;
- tambahkan threshold dan empty-result path.

### Jam 4: generation dan citation

- ubah `PromptAssembler` agar memakai evidence, bukan snapshot penuh;
- minta response terstruktur;
- tampilkan citation source;
- jika evidence kurang, jawab bahwa data belum tersedia;
- jangan memberi angka atau diagnosis tanpa evidence.

### Jam 5: trace dan validasi

Simpan trace minimal:

```text
trace_id
team_id
conversation_id
message_id
route
route_candidates
confidence
retrieved_chunk_ids
retrieval_scores
model
prompt_version
input_tokens
output_tokens
latency_ms
validation_status
abstained
feedback
```

Jangan menyimpan secrets atau isi attachment mentah ke trace.

### Jam 6–8: evaluasi dan laporan

- jalankan golden dataset;
- bandingkan keyword-only dengan retrieval;
- catat failure category;
- cek cross-team isolation;
- cek index rebuild;
- cek response kosong, citation hilang, dan provider timeout;
- hasilkan laporan go/no-go.

### Definition of done untuk vertical slice

Vertical slice dianggap berhasil jika:

- dokumen published dapat di-index ulang secara idempotent;
- query bahasa berbeda menemukan chunk yang sama relevannya;
- team A tidak dapat mengambil chunk team B;
- query tanpa evidence menghasilkan abstention;
- jawaban memiliki citation;
- output invalid tidak ditampilkan sebagai jawaban sukses;
- seluruh langkah dapat direplay dari `trace_id`;
- test tidak hanya memakai satu bentuk kalimat.

## Rencana post: setelah vertical slice menuju production

### Deployment Docker

Tambahkan service berikut:

- `app`: Apache/Laravel runtime;
- `worker`: image yang sama dengan command `php artisan queue:work`;
- `scheduler`: `php artisan schedule:work` atau cron terkelola;
- `postgres`: database utama;
- `redis`: queue dan cache;
- `qdrant`: vector index;
- reverse proxy TLS.

Setiap service harus memiliki health check, volume persistent, batas memory, restart policy, dan backup/restore procedure.

### Index lifecycle

- publish dokumen membuat indexing job;
- indexing job memakai lock/idempotency key;
- dokumen yang berubah membuat version baru;
- versi lama tetap dapat ditelusuri untuk audit;
- reindex penuh dilakukan ketika embedding model berubah;
- retrieval hanya memakai versi published yang aktif.

### Reliability

- retry provider dengan backoff terbatas;
- circuit breaker ketika provider gagal;
- timeout terpisah untuk retrieval, embedding, dan generation;
- fallback ke jawaban grounded sederhana;
- queue monitoring;
- dead-letter handling untuk indexing gagal;
- graceful degradation saat Qdrant tidak tersedia.

### Observability

Gunakan trace berformat konsisten. Langfuse cocok untuk prompt version, trace, feedback, dan experiment. Phoenix cocok jika ingin OpenTelemetry-native tracing dan evaluasi RAG. Pilih satu pada MVP; jangan menjalankan dua platform tanpa kebutuhan.

Dashboard minimum:

- p50/p95/p99 latency;
- provider error rate;
- retrieval empty rate;
- abstention rate;
- citation coverage;
- faithfulness score;
- feedback negatif per route/model/prompt version;
- token dan cost per team;
- index freshness;
- queue backlog.

### Quality gate release

Model, prompt, embedding model, chunk size, top-k, atau reranker tidak boleh diganti langsung di production. Setiap perubahan harus:

1. dijalankan pada golden dataset;
2. dibandingkan dengan baseline;
3. diperiksa untuk regression pada finance, data, knowledge, STT, dan visual;
4. diuji pada beberapa tenant;
5. dirilis canary;
6. memiliki rollback berdasarkan prompt/model/index version.

## Risiko dan cara menguranginya

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Chunk salah | Evidence tidak lengkap | chunk by heading/section, overlap, evaluasi Recall@K |
| Retrieval terlalu luas | LLM melihat noise | top-k kecil, rerank, metadata filter |
| Retrieval terlalu sempit | Jawaban abstain terlalu sering | hybrid search, query expansion, evaluasi recall |
| Dokumen usang | Jawaban outdated | version, published_at, reindex job |
| Cross-team leakage | Pelanggaran privacy | filter team_id sebelum generation, test isolation |
| LLM mengarang | User kehilangan kepercayaan | citation, claim check, abstention, faithfulness eval |
| Qdrant hilang | Knowledge tidak ditemukan | source of truth di PostgreSQL, rebuild index |
| SQLite concurrency | lock dan request gagal | PostgreSQL untuk production |
| Worker berhenti | index stale | health check, alert, retry, backlog metric |
| Image generator menulis label salah | Infografis misleading | render label penting di UI/SVG, bukan mengandalkan image model |
| STT salah | Intent dan advice salah | transcript confidence, confirmation, replay |

## Keputusan yang belum boleh diasumsikan

Sebelum implementasi production, perlu diputuskan:

- PostgreSQL + Qdrant atau PostgreSQL + pgvector;
- provider embedding dan kebijakan pergantian model;
- retention trace dan data privacy;
- apakah citation terlihat oleh user atau hanya internal;
- dokumen mana yang boleh menjadi knowledge per team;
- target user dan ukuran corpus;
- budget VPS, Redis, Qdrant, embedding, dan LLM;
- apakah image generation tetap real-time atau queued;
- apakah ada reviewer manusia untuk knowledge pertanian.

## Kesimpulan

RAG cocok untuk Farmer IC karena masalah utama kita adalah grounding, perubahan knowledge, perbedaan data per team, dan kebutuhan menjelaskan alasan jawaban. Laravel mampu mengorkestrasi seluruh alur tersebut.

Rencana satu hari harus diperlakukan sebagai vertical slice yang menghasilkan bukti, bukan janji bahwa sistem sudah production-ready. Kriteria keberhasilan bukan “AI terdengar pintar”, melainkan evidence ditemukan, route benar, citation tersedia, abstention bekerja, trace dapat direplay, dan failure dapat dikategorikan.

Implementasi production baru dianggap aman setelah melewati golden dataset, tenant isolation test, index rebuild test, provider failure test, latency test, dan canary release.

## Efek domino perubahan dan keamanan migrasi

RAG bukan perubahan yang hanya menyentuh satu service. Perubahan yang aman harus diperlakukan sebagai perubahan platform dengan dependency yang jelas.

### Efek perubahan SQLite ke PostgreSQL

Perubahan ini berdampak pada:

- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`;
- Docker image dan Compose service database;
- volume dan backup database;
- session jika memakai `SESSION_DRIVER=database`;
- cache jika memakai `CACHE_STORE=database`;
- queue jika memakai `QUEUE_CONNECTION=database`;
- migration dan data existing;
- test database dan CI;
- query yang bergantung pada perilaku SQLite;
- lock, transaction, collation, JSON, timestamp, dan tipe boolean.

Di Compose saat ini, session dan cache production diatur ke `file` dan queue diatur ke `sync`, sehingga migrasi database tidak otomatis membuat session/cache/queue menjadi terdistribusi. Setelah PostgreSQL dipakai, keputusan tersebut harus dibuat eksplisit: gunakan Redis untuk session/cache/queue production, atau nyatakan bahwa semuanya tetap file/sync untuk skala kecil.

Migrasi SQLite ke PostgreSQL aman hanya jika dilakukan sebagai cutover terkontrol:

1. backup SQLite dan checksum file;
2. deploy PostgreSQL pada staging;
3. jalankan migration tanpa menghapus database;
4. export/import data dengan mapping dan row count;
5. bandingkan data penting: user, team, membership, conversation, message, attachment, knowledge document, dan usage event;
6. jalankan seluruh feature test dan smoke test AI;
7. lakukan backup PostgreSQL sebelum cutover;
8. aktifkan PostgreSQL melalui environment release;
9. simpan SQLite lama sebagai rollback artifact yang read-only;
10. hapus SQLite hanya setelah periode verifikasi selesai.

Jangan menggunakan `migrate:fresh` atau menghapus volume production sebagai strategi migrasi.

### Efek penambahan Qdrant

Qdrant menambah:

- service Docker baru;
- persistent volume;
- environment URL, collection name, dan credential bila digunakan;
- health check dan monitoring;
- backup atau prosedur rebuild;
- risiko index tertinggal dari PostgreSQL;
- kebutuhan filter tenant di setiap query.

Qdrant tidak boleh menjadi sumber kebenaran utama. Jika volume Qdrant hilang, index harus dapat dibuat ulang dari `ai_knowledge_documents` dan `ai_knowledge_chunks`.

Port Qdrant tidak perlu dibuka ke internet. Laravel mengaksesnya melalui network Docker internal. Hanya reverse proxy yang boleh menerima traffic publik.

### Efek penambahan Redis dan worker

Redis dan worker mengubah perilaku publish knowledge:

```text
publish document → status published/indexing → queue job → indexed/failed
```

Publish tidak lagi berarti dokumen langsung tersedia di retrieval. UI dan admin perlu melihat status `indexing`, `indexed`, atau `failed`. Job harus idempotent, dapat di-retry, dan memiliki dead-letter handling.

Worker tidak menggantikan request streaming chat. Worker digunakan untuk embedding, indexing, reindex, evaluasi, dan pekerjaan async. Chat tetap memiliki timeout, retry, dan fallback provider sendiri.

### Efek pada conversation dan prompt

Conversation saat ini menyimpan `knowledge_snapshot`. Setelah retrieval aktif, snapshot lama dapat menjadi stale atau terlalu besar. Perubahan aman perlu:

- mempertahankan snapshot untuk conversation lama selama masa kompatibilitas;
- menyimpan `knowledge_index_version` atau `retrieval_policy_version`;
- memakai retrieval aktif untuk conversation baru;
- menyediakan fallback jika conversation lama belum memiliki index version;
- menguji format message dan citation lama agar tetap dapat dirender.

Perubahan response ke JSON terstruktur juga dapat memengaruhi frontend, voice playback, history, copy action, dan feedback. Gunakan versioned response contract dan pertahankan parser untuk payload lama selama masa transisi.

### Efek pada biaya dan latency

RAG menambah beberapa operasi baru:

- embedding saat indexing;
- embedding saat query;
- vector search;
- optional reranking;
- token context tambahan;
- penyimpanan trace.

Namun RAG juga dapat mengurangi biaya karena prompt tidak lagi membawa seluruh snapshot knowledge. Ukur p50/p95 latency dan biaya per route. Data service dan knowledge answer yang deterministic harus tetap melewati provider sesedikit mungkin.

### Efek pada STT dan image generation

STT dan image generation tidak otomatis menjadi RAG. Perubahannya adalah:

- STT menambahkan transcript confidence dan kemungkinan koreksi user;
- image generation menerima visual specification yang dibuat dari evidence;
- label penting dirender oleh aplikasi, bukan hanya oleh model gambar;
- trace harus menghubungkan audio/image input, evidence, visual spec, dan output.

Perubahan routing RAG harus diuji agar tidak membuat pertanyaan data masuk ke image generation atau membuat input gambar kehilangan jalur vision.

## Checklist keamanan perubahan

### Sebelum merge

- migration memiliki `up` dan `down` yang dapat diuji;
- tidak ada secret dalam Compose, migration, log, atau frontend;
- test tenant isolation lulus;
- indexer dapat diulang tanpa duplikat;
- Qdrant dapat dibangun ulang dari database;
- response lama tetap dapat diparse;
- feature flag dapat mematikan retrieval dan kembali ke jalur lama;
- ada rollback procedure tertulis;
- backup berhasil dipulihkan di environment terpisah.

### Sebelum production cutover

- staging memakai image Docker yang sama;
- database row count dan checksum diverifikasi;
- migration dijalankan dengan waktu dan lock yang dipahami;
- queue worker dan scheduler memiliki health check;
- Qdrant tidak terekspos ke internet;
- disk VPS memiliki ruang untuk database, attachment, log, dan Qdrant;
- log rotation aktif;
- provider timeout dan retry dibatasi;
- canary team atau internal user melakukan smoke test;
- rollback ke image dan database backup sudah diuji.

## Apakah maintenance RAG dapat dilakukan sendiri

Bisa, jika maintenance dibuat sebagai runbook dan command yang jelas. Anda tidak harus menjadi peneliti machine learning untuk merawat MVP RAG. Pekerjaan maintenance utama adalah governance knowledge, operasi index, evaluasi, dan deployment.

### Maintenance rutin

Harian atau otomatis:

- cek queue backlog dan failed jobs;
- cek index yang tertinggal dari dokumen published;
- cek Qdrant health dan penggunaan disk;
- cek provider error, p95 latency, dan cost;
- cek rate abstention dan feedback negatif.

Mingguan:

- review trace jawaban buruk;
- klasifikasikan failure: routing, retrieval, evidence, generation, STT, visual, atau UI;
- tambahkan kasus nyata ke golden dataset;
- publish perbaikan knowledge melalui review, bukan mengedit index manual;
- jalankan eval baseline.

Setiap perubahan model, prompt, chunking, embedding, top-k, atau reranker:

1. buat version baru;
2. jalankan golden dataset;
3. bandingkan dengan baseline;
4. cek regresi pada semua route;
5. canary release;
6. rollback jika metrik turun.

### Hal yang tidak boleh dilakukan manual

- mengedit vector Qdrant langsung untuk memperbaiki jawaban;
- menghapus chunk production tanpa source document;
- mengganti embedding model tanpa reindex penuh;
- menaikkan threshold hanya agar demo terlihat lebih sering menjawab;
- menghapus trace negatif;
- mengubah prompt production tanpa version dan eval.

### Runbook yang perlu dibuat

Project perlu memiliki command atau prosedur untuk:

```text
php artisan ai:knowledge:index --team=...
php artisan ai:knowledge:reindex --version=...
php artisan ai:knowledge:check
php artisan ai:eval --dataset=golden-v1
php artisan ai:trace:failure --id=...
php artisan ai:health
```

Nama command dapat berubah, tetapi setiap operasi harus dapat dijalankan ulang, memiliki output yang dapat dibaca, dan menghasilkan audit event.

Maintenance akan tetap mudah jika tiga hal dijaga: PostgreSQL sebagai source of truth, Qdrant sebagai index yang dapat dibangun ulang, dan golden dataset sebagai kontrak kualitas.

## Peta efek domino khusus RAG

Efek RAG tidak berhenti pada database atau vector store. RAG mengubah kontrak perilaku AI dari "model menjawab dari prompt" menjadi "sistem mencari evidence lalu model menyusun jawaban". Setiap lapisan di bawah ini perlu diuji sebagai satu sistem.

### 1. Efek pada pemahaman pertanyaan

Query user perlu diubah menjadi bentuk retrieval tanpa menghilangkan maksud aslinya. Ini memengaruhi:

- normalisasi bahasa Indonesia;
- typo dan singkatan;
- kata ganti seperti "itu", "yang tadi", dan "kebun saya";
- konteks percakapan sebelumnya;
- entity seperti komoditas, fase, lokasi, periode, dan jenis aktivitas;
- pertanyaan multi-intent.

Query rewriting dapat membantu retrieval, tetapi hasil rewrite harus disimpan di trace dan tidak boleh menggantikan raw user input. Jika rewrite salah, sistem bisa mengambil evidence untuk pertanyaan yang berbeda.

### 2. Efek pada routing

RAG menambah pilihan jalur, sehingga router tidak boleh hanya memiliki aturan keyword. Kandidat minimal:

```text
data_service
knowledge_retrieval
llm_general
vision
infographic
out_of_scope
clarification
```

Router harus menyimpan kandidat, confidence, ambiguity margin, dan alasan keputusan. Perubahan threshold dapat mengubah banyak output sekaligus, sehingga threshold harus versioned dan masuk regression test.

### 3. Efek pada chunking dan embedding

Chunk size, overlap, heading boundary, bahasa embedding, dan metadata menentukan evidence yang akan ditemukan. Perubahan kecil pada chunking dapat menyebabkan:

- jawaban lebih lengkap atau lebih terpotong;
- citation berubah;
- retrieval latency berubah;
- token prompt bertambah;
- hasil antar bahasa atau antar komoditas tidak seimbang.

Setiap chunk harus menyimpan `chunking_version` dan `embedding_model`. Mengganti salah satunya memerlukan reindex dan perbandingan dengan baseline.

### 4. Efek pada kualitas retrieval

Retrieval yang salah dapat terlihat seperti halusinasi LLM. Karena itu failure harus dipisahkan:

| Gejala | Kemungkinan sumber |
| --- | --- |
| Tidak ada evidence | query understanding, indexing, filter, atau threshold |
| Evidence tidak relevan | embedding, chunking, query rewrite, atau reranker |
| Evidence benar tetapi jawaban salah | generator atau prompt |
| Jawaban benar tanpa citation | response schema atau renderer |
| Data team lain muncul | authorization/filter bug |

Jangan memperbaiki semua gejala dengan mengganti model. Trace harus menunjukkan lapisan yang gagal.

### 5. Efek pada prompt dan model

Prompt tidak lagi hanya berisi policy dan seluruh knowledge snapshot. Prompt harus memiliki:

- raw question;
- conversation state yang relevan;
- evidence terpilih;
- aturan citation;
- aturan abstention;
- output schema;
- batasan domain dan permission.

Perubahan prompt, model, top-k, reranker, atau temperature dapat mengubah jawaban walaupun user input sama. Karena itu semua komponen harus mempunyai version identifier.

### 6. Efek pada response contract dan UI

Response RAG sebaiknya membawa metadata internal:

```text
answer
citations
claims
evidence_ids
abstained
follow_up_question
route
trace_id
```

Frontend perlu memutuskan:

- apakah citation terlihat sebagai sumber;
- bagaimana menampilkan "data belum cukup";
- bagaimana membedakan jawaban grounded dan jawaban umum;
- bagaimana menampilkan status indexing;
- bagaimana feedback user dikaitkan dengan trace.

Jangan membuat semua response RAG menjadi kartu visual. Presentation layer tetap mengikuti intent dan `presentation` contract.

### 7. Efek pada STT

STT dapat memperkenalkan kesalahan sebelum retrieval dimulai. Contoh: "kedelai" terbaca sebagai kata lain, lalu retriever mengambil knowledge yang salah. Trace harus menyimpan:

- audio request id;
- transcript final;
- confidence;
- alternatif transcript jika tersedia;
- apakah user mengoreksi transcript;
- query yang benar-benar dipakai untuk retrieval.

Jika confidence rendah pada entity penting, sistem harus meminta konfirmasi sebelum memberi panduan spesifik.

### 8. Efek pada image generation

RAG dapat memperbaiki isi infografis, tetapi tidak otomatis memperbaiki kualitas gambar. Jalur aman:

```text
retrieve evidence
→ extract verified facts
→ create VisualSpec
→ generate illustration
→ render labels, numbers, and steps in UI/SVG
→ validate text against VisualSpec
```

Image model tidak boleh menjadi sumber fakta. Asset generic tidak boleh menjadi fallback diam-diam jika user meminta komoditas atau proses tertentu; sistem harus menyatakan bahwa visual khusus tidak tersedia atau memakai asset yang relevan dengan label yang jujur.

### 9. Efek pada latency dan biaya

RAG menambah satu atau lebih network hop sebelum generation. Jalur yang berbeda harus memiliki budget berbeda:

```text
data lokal:       cepat dan deterministik
knowledge RAG:    retrieval + generation
vision:           upload + vision model
infographic:      retrieval + image model, paling lambat
```

UI harus menampilkan tahap yang benar, bukan satu spinner generik. Trace harus memisahkan:

- route time;
- embedding time;
- retrieval time;
- reranking time;
- provider first-token time;
- generation time;
- validation time.

### 10. Efek pada maintenance ilmu

Maintenance RAG bukan hanya menambah dokumen. Tim perlu merawat empat aset:

1. **corpus**: apakah sumber knowledge benar, terbaru, dan disetujui;
2. **index**: apakah semua published document sudah ter-index;
3. **behavior dataset**: apakah failure nyata sudah menjadi regression case;
4. **pipeline configuration**: chunking, embedding, top-k, reranker, threshold, prompt, dan model version.

Jika jawaban salah, proses perbaikannya harus menjawab pertanyaan berikut secara berurutan:

```text
Apakah user dipahami dengan benar?
Apakah route benar?
Apakah evidence yang benar ditemukan?
Apakah evidence cukup?
Apakah model setia pada evidence?
Apakah citation dan renderer benar?
```

Dengan alur ini, perbaikan dapat dilakukan berdasarkan ilmu dan bukti, bukan dengan menambah keyword atau mengganti model secara acak.

