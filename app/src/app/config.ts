export interface RuntimeConfig { apiUrl: string; appName: string; assetsUrl?: string }

const element = document.getElementById('fr-config');
const runtime: RuntimeConfig = element?.textContent
  ? JSON.parse(element.textContent)
  : { apiUrl: '/wp-json/fitet-ricevute/v1/', appName: 'FITET Ricevute' };

// Only the same WordPress origin may receive the app's authentication headers.
const url = new URL(runtime.apiUrl, window.location.origin);
if (url.origin !== window.location.origin) throw new Error('Origine API non valida');
export const config = { ...runtime, apiUrl: url.href };

// Works with both /wp-json/... and index.php?rest_route=/... (plain permalinks).
export function apiUrl(path: string): string {
  const url = new URL(config.apiUrl);
  if (url.searchParams.has('rest_route')) {
    url.searchParams.set('rest_route', url.searchParams.get('rest_route')!.replace(/\/$/, '') + '/' + path);
  } else {
    url.pathname = url.pathname.replace(/\/$/, '') + '/' + path;
  }
  return url.href;
}

export function isAppApi(value: string): boolean {
  const base = new URL(config.apiUrl);
  const target = new URL(value, window.location.origin);
  if (target.origin !== base.origin) return false;
  if (base.searchParams.has('rest_route')) {
    return target.pathname === base.pathname && (target.searchParams.get('rest_route') || '').startsWith(base.searchParams.get('rest_route')!);
  }
  return target.pathname.startsWith(base.pathname);
}
