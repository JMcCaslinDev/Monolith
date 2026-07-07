import { execSync } from 'node:child_process';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

// ponytail: spindb users create is idempotent enough for local dev setup
try {
  execSync('pnpm exec spindb users create monolith monolith -p monolith -d monolith', {
    cwd: root,
    stdio: 'pipe',
  });
  console.log('DB user monolith ready.');
} catch {
  console.log('DB user monolith already exists (ok).');
}
