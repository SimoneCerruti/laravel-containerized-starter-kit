---
paths:
    - app/Console/Commands/**
---

# Console commands

- Signature convention: `domain:verb-object`.
    - `domain` is the plural, kebab-case form of the module (eg: analytics) or the full model name the command operates on — never a shortened or generic stand-in.
    - `verb-object` is a kebab-case action descriptor.
- Declare command metadata via PHP attributes whenever one exists for it, instead of the equivalent property: `#[Signature('...')]` instead of `protected $signature`, and the same for `#[Description('...')]`, `#[Help('...')]`, `#[Usage('...')]`, `#[Aliases([...])]`, `#[Hidden]`, etc.
