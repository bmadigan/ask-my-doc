# Task Context System

Persist context across sessions for multi-day features.

## Structure

```
context/
└── dev/
    └── active/
        └── [task-name]/
            ├── [task-name]-plan.md      # Implementation plan
            ├── [task-name]-context.md   # Key files and decisions
            └── [task-name]-tasks.md     # Checklist of work
```

## Usage

### Creating Context

For complex features spanning multiple sessions:

```bash
mkdir -p .claude/context/dev/active/enterprise-auth/
```

Create three files:

1. **[task]-plan.md** - High-level implementation plan
2. **[task]-context.md** - Key files, decisions, gotchas
3. **[task]-tasks.md** - Checklist with status

### Resuming Work

Start a new session with:

> "Continue working on enterprise-auth"

Claude will read the context files and pick up where you left off.

### Completing Tasks

Move completed task directories to an archive:

```bash
mv .claude/context/dev/active/enterprise-auth .claude/context/dev/archive/
```

## Template Files

See `_templates/` for starter templates.

