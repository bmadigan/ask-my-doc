#!/usr/bin/env node

/**
 * Constitution Advisory Hook
 *
 * Provides gentle reminders about AI Constitution principles based on prompt context.
 * Advisory only - does not block any responses.
 *
 * Inspired by Anthropic's Claude Constitution approach:
 * https://www.anthropic.com/news/claude-new-constitution
 */

const fs = require('fs');
const path = require('path');

const prompt = (process.argv[2] || '').toLowerCase();

// Don't process empty or very short prompts
if (prompt.length < 15) {
  process.exit(0);
}

// Define constitution principle triggers
const constitutionTriggers = {
  clarity: {
    keywords: ['explain', 'simplify', 'confusing', 'understand', 'junior', 'beginner', 'complex', 'complicated'],
    patterns: [/how does.*work/i, /what is/i, /can you explain/i, /i don't understand/i],
    reminder: 'Clarity: Prefer simple explanations. Would a junior dev understand?'
  },
  honesty: {
    keywords: ['not sure', 'uncertain', 'verify', 'correct', 'accurate', 'fact', 'true', 'confirm'],
    patterns: [/is it true/i, /am i right/i, /does.*actually/i, /is this correct/i],
    reminder: 'Honesty: State uncertainty clearly. Never fabricate facts or APIs.'
  },
  safety: {
    keywords: ['dangerous', 'risky', 'security', 'vulnerability', 'hack', 'exploit', 'delete all', 'drop table'],
    patterns: [/is this safe/i, /could this.*harm/i, /security risk/i],
    reminder: 'Safety: If declining, explain why and offer alternatives. Speak like a teammate.'
  },
  seniorMindset: {
    keywords: ['tradeoff', 'trade-off', 'alternative', 'edge case', 'architecture', 'design', 'best practice', 'should i'],
    patterns: [/which.*better/i, /pros and cons/i, /what if/i, /should i use/i, /best way/i],
    reminder: 'Senior Mindset: Think in tradeoffs. Call out edge cases. Suggest simpler solutions.'
  }
};

// Check which principles might be relevant
const relevantPrinciples = [];

for (const [principle, config] of Object.entries(constitutionTriggers)) {
  let triggered = false;

  // Check keywords
  if (config.keywords.some(kw => prompt.includes(kw))) {
    triggered = true;
  }

  // Check patterns
  if (!triggered && config.patterns.some(pattern => pattern.test(prompt))) {
    triggered = true;
  }

  if (triggered) {
    relevantPrinciples.push(config.reminder);
  }
}

// Output advisory if principles triggered (limit to 2)
if (relevantPrinciples.length > 0) {
  const toShow = relevantPrinciples.slice(0, 2);

  console.error('');
  console.error('┌─────────────────────────────────────────');
  console.error('│ 📜 CONSTITUTION REMINDER');
  console.error('├─────────────────────────────────────────');

  for (const reminder of toShow) {
    console.error(`│ • ${reminder}`);
  }

  console.error('└─────────────────────────────────────────');
  console.error('');
}

process.exit(0);
