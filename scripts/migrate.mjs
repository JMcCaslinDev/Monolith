import { readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const dir = join(root, 'database/migrations');
const files = readdirSync(dir).filter((f) => f.endsWith('.sql')).sort();

for (const file of files) {
  const path = join(dir, file);
  process.stdout.write(`→ ${file}... `);
  try {
    execSync(`pnpm exec spindb run monolith "${path}"`, { cwd: root, stdio: 'pipe' });
    console.log('ok');
  } catch (e) {
    console.error('failed');
    process.stderr.write(e.stderr?.toString() || e.message);
    process.exit(1);
  }
}

console.log(`Done (${files.length} files).`);
