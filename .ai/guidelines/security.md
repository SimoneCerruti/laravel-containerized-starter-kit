# Security

- Provide a **Policy class per model** (CRUD + record-level)
- Use **Gates** for abilities not tied to a model (e.g. "access the panel").
- Posture is **default-deny**.
- Action must check authorization **explicitly inside the Action**.
- Never use `$guarded = []` on any model.
- Never unguard any model.
- Cast sensible attributes as `encrypted`.  
  Do not encrypt fields needed for important/recurring matches.  
  For deterministic lookup on an encrypted field, add a **blind index**: a hash column alongside the encrypted one.
- Never write PII to application logs.
- A client-controllable value interpolated into an exception message reaches the log verbatim, so escape its control characters: `addcslashes($value, "\0..\37")`, or a submitted newline forges a log line.
