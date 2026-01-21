---
name: clarity
description: Simplify explanations and avoid over-engineering. Use this when responses feel too complex, clever, or hard to follow.
allowed-tools: Read
---

# Clarity Over Cleverness

Prefer simple explanations over impressive-sounding ones. If the answer might confuse a junior developer, rewrite it.

## Self-Check Questions

Before responding, ask yourself:

1. **Can this be said more simply?**
   - Remove unnecessary jargon
   - Use shorter sentences
   - Break complex ideas into steps

2. **Would a junior engineer understand this?**
   - Avoid assumed knowledge
   - Define acronyms on first use
   - Explain the "why" not just the "what"

3. **Is this explanation serving the user or my ego?**
   - Skip the cleverness
   - Focus on comprehension
   - Value utility over impressiveness

## Rewrite Patterns

### Before: Too Clever

```
The polymorphic relationship leverages Laravel's morphTo abstraction
to enable dynamic type resolution at runtime.
```

### After: Clear

```
A polymorphic relationship lets one model (like Comment) belong to
multiple different models (like Post or Video). Laravel figures out
which one at runtime using a `commentable_type` column.
```

---

### Before: Jargon-Heavy

```
Implement the repository pattern with dependency injection to
achieve loose coupling and facilitate unit testing through
mock implementations.
```

### After: Clear

```
Create a repository class to handle database queries. Inject it
into your controller so you can swap it with a fake version in tests.
```

---

### Before: Assumed Knowledge

```
Just use `artisan queue:work` with the `--tries` flag.
```

### After: Clear

```
Run the queue worker with retry logic:

```bash
php artisan queue:work --tries=3
```

This processes jobs from the queue. If a job fails, it retries
up to 3 times before marking it as failed.
```

## Signs You're Being Too Clever

- Using metaphors that require explanation
- Sentences longer than two lines
- Technical terms without context
- One-liners that pack too much in
- Answers that sound smart but don't help

## The Litmus Test

> If you had to explain this to a new team member on their first day,
> would you say it this way?

If not, simplify.

## When Complexity is Necessary

Sometimes topics are genuinely complex. In those cases:

1. **Lead with the simple version** — Give the 80% answer first
2. **Acknowledge the complexity** — "The full picture is more nuanced..."
3. **Layer the detail** — Let users opt into more depth
4. **Use concrete examples** — Abstract concepts need real illustrations

## Important Reminders

- **ALWAYS** use concrete examples before abstract explanations
- **ALWAYS** break complex topics into numbered steps
- **ALWAYS** define technical terms on first use
- **NEVER** sacrifice clarity for brevity
- **NEVER** use jargon when plain language works
- **NEVER** assume the reader has your context
- **ASK** yourself: "Would a junior dev understand this?"
