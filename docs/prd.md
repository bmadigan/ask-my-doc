Here’s the cleanest way to do **Ask My Doc** with SQLite + Livewire + Overpass.

# Direct answer (simple as possible)

You don’t need a vector database. Keep SQLite.
Store each chunk’s embedding as JSON in a table, and use a tiny **Python op** (called via Overpass) to compute cosine similarity and return the top-K chunks. Overpass already gives you embedding + chat helpers; you’ll just add one custom “sqlite\_search” op. ([GitHub][1])

# Why this works (step-by-step)

* **Overpass** can generate embeddings and run custom Python operations you define. We’ll use `Overpass::generateEmbedding()` during ingest, and `Overpass::execute('sqlite_search', …)` to rank chunks. If you want to add a final answer, call `Overpass::chat(...)` with the retrieved chunks as context. ([GitHub][1])
* **SQLite** stores: documents, chunks, and each chunk’s embedding (JSON array). No pgvector, Faiss, or Chroma needed for a demo.
* **Python** reads the same SQLite DB path (passed in your execute payload), embeds the query, computes cosine similarity over stored vectors, and returns top-K with scores.

---

# PRD for Claude Code (implementation-ready, no coding instructions)

## 1) Product summary

**Name:** Ask My Doc
**Goal:** Let a user paste/upload a document and ask questions grounded to that document.
**Outcome:** Accurate short answers + citations to the exact source chunks.

## 2) Success metrics

* Time to first answer < 5s for small docs (<100 chunks) on a laptop.
* ≥90% of answers include at least one relevant citation (top-K chunk).
* Zero runtime crashes in the bridge (robust error handling paths).

## 3) Tech stack & dependencies

* **Backend/UI:** Laravel 12, Livewire v3, PHP 8.2+
* **Data:** SQLite (default `database/database.sqlite`)
* **AI bridge:** `bmadigan/overpass` (Composer) with published config and example scripts enabled. Use `Overpass::testConnection()`, `::generateEmbedding()`, `::chat()`, and `::execute()` as needed. ([GitHub][1])
* **Python:** Python 3.10+, `numpy` (cosine similarity), `openai` (used inside the example Python script Overpass calls)
* **Env:** `OPENAI_API_KEY`, `OVERPASS_SCRIPT_PATH` pointing to the Python entrypoint installed by Overpass. ([GitHub][1])

## 4) Data model (SQLite)

Tables (minimal):

* **documents**: id, title, bytes, original\_filename, created\_at
* **chunks**: id, document\_id (FK), chunk\_index (int), content (text), embedding\_json (text), token\_count (int), created\_at
* **queries** (optional analytics): id, document\_id, question, top\_k\_returned, latency\_ms, created\_at

**Notes**

* `embedding_json` stores a JSON array of floats (e.g., 1536 dims).
* Indexes: `chunks(document_id, chunk_index)`.

## 5) User flows

### 5.1 Ingest flow

1. User lands on “Ingest” screen. Two inputs:

   * Paste text (MVP)
   * Optional upload: `.txt` / `.md` only (keep simple; PDF support is out-of-scope for v1).
2. App chunks the text server-side:

   * Fixed size chunks, \~1,000–1,200 characters with 200 overlap (keep token logic out of v1).
3. For each chunk:

   * Call `Overpass::generateEmbedding($chunkText)` and store the vector in `embedding_json`. (From README “Generate embeddings”.) ([GitHub][1])
4. Save `documents` + `chunks`, show a success state with chunk count.

### 5.2 Ask flow

1. User opens “Ask” screen, selects a document, types a question.
2. App calls `Overpass::execute('sqlite_search', [ 'db_path' => base_path('database/database.sqlite'), 'document_id' => …, 'query' => …, 'k' => 6, 'min_score' => 0.2 ])`.
3. Python op returns top-K chunk ids + scores.
4. App concatenates those chunks (with separators) into a **context block** and calls `Overpass::chat([...])` with:

   * **system**: “Answer ***only*** from the provided context. If unsure, say you don’t know.”
   * **user**: the question
   * **context**: top-K chunk contents (cite with `[chunk #]` markers)
     (README shows “Chat operations”.) ([GitHub][1])
5. Show the answer + inline citations mapping to chunk indices.
6. Log query metadata (latency, K) in `queries`.

## 6) Python ops (called by Overpass)

**Entry file:** `overpass-ai/main.py` (or the path set in `OVERPASS_SCRIPT_PATH`). Overpass maps PHP calls to Python functions (per README pattern). ([GitHub][1])

### 6.1 `health_check`

* Returns `{ success: true, data: { status: 'healthy' } }` for Overpass diagnostics. (Used by a “Test connection” button.) ([GitHub][1])

### 6.2 `sqlite_search`

**Input:**
`{ db_path, document_id, query, k, min_score }`

**Steps:**

1. Embed the `query` using OpenAI in Python (use the same embedding model the package uses for `generateEmbedding`).
2. Open `db_path` via `sqlite3`, select `id, content, embedding_json` from `chunks` where `document_id = ?`.
3. Parse `embedding_json` into vectors, compute cosine similarity vs. query embedding with `numpy`.
4. Sort desc, filter by `min_score`, return top-K as:

```
{
  success: true,
  data: {
    results: [
      { chunk_id, score, preview: content_snippet }
    ]
  }
}
```

### 6.3 (Optional) `summarize`

* If you prefer to do the final answer in Python, expose a `summarize` op that receives `{ question, top_chunks }` and returns a drafted answer. (You can also just use `Overpass::chat()` from PHP; both are fine per README.) ([GitHub][1])

## 7) Livewire screens & components

* **IngestDocument**

  * Inputs: title, paste area, (optional) .txt/.md upload
  * Actions: “Chunk & Embed”
  * Output: chunk count, time taken
* **AskDocument**

  * Inputs: document selector, question, K slider (3–10)
  * Actions: “Ask”
  * Output: answer panel + collapsible “Sources” (list top-K with scores and previews)
* **OverpassStatusCard**

  * Button: “Test Overpass” → `Overpass::testConnection()` and show result. ([GitHub][1])

## 8) Prompts (deterministic structure)

* **System prompt (chat):**
  “You are a helpful assistant answering ONLY from the provided context. If the context does not contain the answer, say ‘I don’t know based on the document.’ Keep answers concise (3–6 sentences) and cite chunks like \[1], \[2], …”
* **Context formatting:**

  ```
  [1] <chunk content>
  ---
  [2] <chunk content>
  ...
  ```

## 9) Config & environment

* `.env`:

  * `OPENAI_API_KEY=` (required)
  * `OVERPASS_SCRIPT_PATH=/full/path/to/overpass-ai/main.py`
  * Optional: `OVERPASS_TIMEOUT`, `OVERPASS_MAX_OUTPUT` (see README config). ([GitHub][1])
* Artisan setup (documented for reproducibility):

  * `composer require bmadigan/overpass`
  * `php artisan overpass:install --with-examples`
  * `php artisan vendor:publish --tag="overpass-config"` ([GitHub][1])

## 10) Error handling & edge cases

* **Bridge health:** show explicit status via `testConnection()` in a settings panel. ([GitHub][1])
* **Timeouts:** set a reasonable Overpass timeout (e.g., 30–60s); surface friendly errors.
* **Large docs:** cap at \~100 chunks for v1; truncate paste input (show warning).
* **Empty/low scores:** if `sqlite_search` returns no result above `min_score`, respond: “I don’t know based on the document.”
* **PII/leaks:** system prompt explicitly forbids answering beyond context.

## 11) Non-functional constraints

* **Latency target:** ingest ≤ 30s for \~50 chunks; Q\&A ≤ 3–5s.
* **Privacy:** all processing local to server; no third-party storage.
* **Observability:** log each `execute` call (duration, op name, result size) for debugging.

## 12) Acceptance criteria

* Can ingest a pasted doc, see chunk count, and ask at least three distinct questions with sensible citations.
* Overpass health check succeeds from the UI.
* Removing a document removes associated chunks.
* Setting K to 1 still returns the best single chunk.

## 13) Out of scope (v1)

* PDF/Docx parsing, streaming responses, multi-document RAG, user auth/tenancy, queues, rate-limit backoff, and advanced ranking.

## 14) Roadmap (v2+)

* Add PDF parsing; switch search to FAISS/Chroma (then you could use `Overpass::vectorSearch()` end-to-end). ([GitHub][1])
* Add multi-doc selection and re-ranking; enable queues for ingest; add feedback thumbs to fine-tune prompts.

---

# Alternatives you could choose

1. **Use Overpass `vectorSearch()`** right away by wiring a tiny FAISS/Chroma store in the Python script and persisting an index file to disk instead of SQLite; this matches the README’s vector search example exactly. ([GitHub][1])
2. **Do similarity in PHP** (load embeddings into memory and compute cosine). Simpler code path, but slower in PHP and larger JSON payloads.
3. **pgvector** (Postgres) later if you want bigger docs and SQL-native ANN.

---

# Action plan (what to build, in order)

1. Install and configure Overpass; add health card in the UI. ([GitHub][1])
2. Migrations: `documents`, `chunks`.
3. IngestDocument component (chunking + `generateEmbedding`). ([GitHub][1])
4. Python `sqlite_search` op + AskDocument component.
5. Chat answer formatting + citations; basic analytics logging.
6. Polish UX (empty states, errors, loading spinners).


## Technical:
You don’t need a vector database. Keep SQLite.
Store each chunk’s embedding as JSON in a table, and use a tiny Python op (called via Overpass) to compute cosine similarity and return the top-K chunks. Overpass already gives you embedding + chat helpers; you’ll just add one custom “sqlite_search” op.
GitHub

Why this works (step-by-step)

Overpass can generate embeddings and run custom Python operations you define. We’ll use Overpass::generateEmbedding() during ingest, and Overpass::execute('sqlite_search', …) to rank chunks. If you want to add a final answer, call Overpass::chat(...) with the retrieved chunks as context.
GitHub

SQLite stores: documents, chunks, and each chunk’s embedding (JSON array). No pgvector, Faiss, or Chroma needed for a demo.

Python reads the same SQLite DB path (passed in your execute payload), embeds the query, computes cosine similarity over stored vectors, and returns top-K with scores.
