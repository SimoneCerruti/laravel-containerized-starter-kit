- Reach for CodeGraph BEFORE grep/find or reading files when you need to understand or locate code
  — this repo is indexed by it (a `.codegraph/` directory exists at the repo root):
  - **MCP tool** (when available): `codegraph_explore` answers most code questions in one call — the
    relevant symbols' verbatim source plus the call paths between them, including dynamic-dispatch
    hops grep can't follow. Name a file or symbol in the query to read its current line-numbered
    source. If it's listed but deferred, load it by name via tool search.
  - **Shell** (always works): `codegraph explore "<symbol names or question>"` prints the same
    output.
