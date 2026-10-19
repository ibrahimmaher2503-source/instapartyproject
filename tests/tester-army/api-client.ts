import { readFileSync } from 'node:fs';
import { expect } from 'e2e';

export const fixtures = () => JSON.parse(readFileSync('.e2e/fixtures.json', 'utf8'));
const tokens = new Map<string, string>();

// API credentials stay in the transport; no token or password is passed to an agent.
export async function apiClient(baseUrl: string, role: string) {
  const key = `${baseUrl}:${role}`;
  let token = tokens.get(key);
  if (!token) {
  const account = JSON.parse(readFileSync('.e2e/credentials.json', 'utf8'))[role];
  const login = await fetch(new URL('/api/v1/login', baseUrl), {
    method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ login: account.username, password: account.password }),
  });
  await expect(login.status).toBe(200);
  token = (await login.json()).data.token;
  if (typeof token !== 'string' || !token) throw new Error('Fixture login did not return a token');
  tokens.set(key, token);
  }
  return async (method: string, path: string, body?: unknown, headers: Record<string, string> = {}) => {
    const response = await fetch(new URL(path, baseUrl), {
      method, headers: { Accept: 'application/json', 'Accept-Language': 'en', 'Content-Type': 'application/json', Authorization: `Bearer ${token}`, ...headers },
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    return { status: response.status, body: response.status === 204 ? null : await response.json() };
  };
}
