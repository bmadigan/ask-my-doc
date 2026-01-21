#!/usr/bin/env node

/**
 * Plan Context Reminder Hook
 *
 * Reminds to create context files when exiting plan mode for complex features.
 * From GeoCodio: https://www.geocod.io/code-and-coordinates/2025-12-12-how-we-use-claude-code/
 */

const fs = require('fs');
const path = require('path');

// Check if we just completed a planning discussion
// This is a simple heuristic - in practice you might want more sophisticated detection
const response = process.env.CLAUDE_RESPONSE || '';

const planningIndicators = [
  'implementation plan',
  'here\'s the plan',
  'step-by-step plan',
  'proposed approach',
  'let me outline',
  'multi-day feature',
  'complex feature'
];

const isPlanningResponse = planningIndicators.some(indicator =>
  response.toLowerCase().includes(indicator)
);

if (isPlanningResponse) {
  console.error('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.error('📋 PLAN MODE REMINDER');
  console.error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.error('');
  console.error('For multi-day features, consider creating context files:');
  console.error('');
  console.error('  mkdir -p .claude/context/dev/active/[task-name]/');
  console.error('');
  console.error('  Create three files:');
  console.error('  • [task-name]-plan.md      # Implementation plan');
  console.error('  • [task-name]-context.md   # Key files and decisions');
  console.error('  • [task-name]-tasks.md     # Checklist of work');
  console.error('');
  console.error('Resume later: "Continue working on [task-name]"');
  console.error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
}

process.exit(0);

