#!/usr/bin/env node

/**
 * Skill Activation Hook
 *
 * Analyzes prompts and suggests relevant skills based on keyword and intent matching.
 * Inspired by GeoCodio's approach: https://www.geocod.io/code-and-coordinates/2025-12-12-how-we-use-claude-code/
 */

const fs = require('fs');
const path = require('path');

const prompt = (process.argv[2] || '').toLowerCase();

// Don't process empty prompts or very short ones
if (prompt.length < 10) {
  process.exit(0);
}

// Load skill rules
const rulesPath = path.join(__dirname, 'skill-rules.json');
if (!fs.existsSync(rulesPath)) {
  process.exit(0);
}

const rules = JSON.parse(fs.readFileSync(rulesPath, 'utf8'));

// Find matching skills
const matchedSkills = [];

for (const [skillName, config] of Object.entries(rules.skills)) {
  const triggers = config.promptTriggers;
  let matched = false;
  let matchType = '';

  // Keyword matching
  if (triggers.keywords && triggers.keywords.some(kw => prompt.includes(kw.toLowerCase()))) {
    matched = true;
    matchType = 'keyword';
  }

  // Intent pattern matching (regex)
  if (!matched && triggers.intentPatterns) {
    for (const pattern of triggers.intentPatterns) {
      if (new RegExp(pattern, 'i').test(prompt)) {
        matched = true;
        matchType = 'intent';
        break;
      }
    }
  }

  if (matched) {
    matchedSkills.push({ name: skillName, matchType });
  }
}

// Output suggestions if skills matched
if (matchedSkills.length > 0) {
  // Limit to top 3 most relevant skills
  const topSkills = matchedSkills.slice(0, 3);

  console.error('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.error('🎯 SKILL ACTIVATION CHECK');
  console.error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.error('');
  console.error('📚 RECOMMENDED SKILLS:');

  for (const skill of topSkills) {
    console.error(`   → ${skill.name}`);
  }

  console.error('');
  console.error('ACTION: Consider loading skill with /skill [name]');
  console.error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
}

process.exit(0);

