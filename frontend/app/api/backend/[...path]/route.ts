import { NextRequest, NextResponse } from 'next/server';

const allowed = new Set(['health', 'hackathons', 'candidates']);
const methods = new Set(['GET', 'POST', 'OPTIONS']);

async function forward(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  const { path } = await context.params;
  if (!methods.has(request.method)) {
    return NextResponse.json({ error: { code: 'method_not_allowed', message: 'Method not allowed.' } }, { status: 405 });
  }
  if (!path.length || !allowed.has(path[0])) {
    return NextResponse.json({ error: { code: 'not_found', message: 'Backend route not allowed.' } }, { status: 404 });
  }

  const base = process.env.PHP_API_BASE_URL;
  if (!base) {
    return NextResponse.json({ error: { code: 'configuration_error', message: 'PHP_API_BASE_URL is not configured.' } }, { status: 503 });
  }

  const target = new URL(`/api/v1/${path.map(encodeURIComponent).join('/')}`, `${base.replace(/\/$/, '')}/`);
  target.search = request.nextUrl.search;
  const headers = new Headers();
  const contentType = request.headers.get('content-type');
  if (contentType) headers.set('content-type', contentType);
  const token = process.env.PHP_REVIEW_API_TOKEN;
  if (token && path[0] === 'candidates') headers.set('authorization', `Bearer ${token}`);

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);
  let response: Response;
  try {
    response = await fetch(target, {
      method: request.method,
      headers,
      body: request.method === 'GET' || request.method === 'HEAD' || request.method === 'OPTIONS' ? undefined : await request.text(),
      cache: 'no-store',
      signal: controller.signal,
    });
  } catch {
    return NextResponse.json({ error: { code: 'backend_unavailable', message: 'The PHP API could not be reached.' } }, { status: 503 });
  } finally {
    clearTimeout(timeout);
  }
  const body = await response.text();
  return new NextResponse(body, {
    status: response.status,
    headers: { 'content-type': response.headers.get('content-type') || 'application/json' },
  });
}

export const GET = forward;
export const POST = forward;
export const OPTIONS = forward;
