# Best Practices

- Stick to community best practices. Consult documentation and/or perform a web search when in need to find opinionated solutions.
- Consistency wins over everything. If the project has an established pattern, follow it for future implementations and refactors.
- Any db-relevant operation performed on a model class (eg: `Model::create()`, `Model::delete()`, `Model::update()`, ...) must run through the query builder `query` method.  
  Eg: The following is WRONG `Model::create(...)`, while the following is CORRECT `Model::query()->create(...)`.
- Prefer immutability whenever possible.
- When creating a new model or controller, generate a factory too.
- When generating links to other pages, prefer named routes and the `route()` function.
- Update existing docs when editing/refactoring a feature. Do not append at the end or add a "modified in date" section. Edit the doc file to keep it natural, organized and fluently readable, by editing its content.
- Use conventional commits (https://www.conventionalcommits.org/en/v1.0.0/) to craft commit messages. This is a MUST.
