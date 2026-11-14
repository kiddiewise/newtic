// Read-only scan reporting: scanner matches are candidates, not automatic edits.
import { readFileSync, appendFileSync } from 'node:fs';

const input = process.argv[2] || 'modern-web-report/findings.json';
const result = JSON.parse(readFileSync(input, 'utf8'));
if (!Array.isArray(result.guides) || typeof result.totalFindings !== 'number') {
  throw new Error('Unexpected Modern Web Guidance scan result format');
}
const rows = [
  '## Modern Web Guidance — NEWTIC',
  '',
  '**Review required:** Findings are static suggestions, not confirmed defects.',
  'Cross-browser support, accessibility, current design and fallback paths must be checked before any code change.',
  '',
  `- Candidate findings: **${result.totalFindings}**`,
  `- Checked rules: **${result.activeRuleCount ?? 'unknown'}**`,
  `- Guides with candidates: **${result.guides.length}**`,
  '',
  '| Guide | File | Line | Candidate |',
  '|---|---|---:|---|',
];
for (const guide of result.guides) {
  for (const rule of guide.rules || []) {
    for (const finding of rule.findings || []) {
      const safe = (value) => String(value ?? '').replaceAll('|', '\\|').replace(/[\r\n]/g, ' ');
      rows.push(`| ${safe(guide.guideId)} | ${safe(finding.file)} | ${Number(finding.line || 0)} | ${safe(finding.message)} |`);
    }
  }
}
if (!result.totalFindings) rows.push('| — | — | — | No deterministic candidates found |');
rows.push('', 'The scanner covers a subset of the guidance catalog; it does not measure overall site quality.');
const report = rows.join('\n') + '\n';
console.log(report);
if (process.env.GITHUB_STEP_SUMMARY) {
  appendFileSync(process.env.GITHUB_STEP_SUMMARY, report);
}
