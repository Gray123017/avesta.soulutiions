/* Staging-only routes. Preserve only validated calculator options. */
import {rates, frequencies} from './domain.js?v=20261003-steady-navigation';
export const STAGE_BASE = '/avesta-integrated-stage7b';
export const publicPages = new Set(['software','lending','it','calculator','how-it-works','currency','about','contact']);
export function calculatorOptions(params) {
  const result = new URLSearchParams();
  const weeks = params.get('weeks');
  if (/^[1-4]$/.test(weeks || '') && Object.hasOwn(rates, weeks)) result.set('weeks', weeks);
  const raw = params.get('amount');
  if (raw && /^\d+(?:\.\d{1,2})?$/.test(raw)) {
    const amount = Number(raw);
    if (Number.isFinite(amount) && amount >= .01 && amount <= 1000000) result.set('amount', amount.toFixed(2));
  }
  if (frequencies.includes(params.get('frequency'))) result.set('frequency', params.get('frequency'));
  const start = params.get('start');
  if (start && /^\d{4}-\d{2}-\d{2}$/.test(start)) {
    const d = new Date(start + 'T12:00:00Z');
    if (Number.isFinite(d.getTime()) && d.toISOString().slice(0,10) === start) result.set('start', start);
  }
  return result;
}
export function hostedURL(path, origin = globalThis.location.origin) {
  const u = new URL(path, origin);
  if (u.origin !== origin || !['http:', 'https:'].includes(u.protocol)) return path;
  let pathname = u.pathname;
  if (pathname === STAGE_BASE) pathname = '/';
  else if (pathname.startsWith(STAGE_BASE + '/')) pathname = pathname.slice(STAGE_BASE.length);
  if (pathname === '/downloads.php' || pathname === '/downloads') return STAGE_BASE + '/index.php?page=software' + u.hash;
  const legacy = {'/apply':'/apply.php','/portal':'/portal.php','/privacy':'/legal.php?p=privacy','/terms':'/legal.php?p=terms','/complaints':'/legal.php?p=complaints'};
  if (pathname === '/apply' || pathname === '/apply.php') {
    const options = calculatorOptions(u.searchParams);
    return STAGE_BASE + '/apply.php' + (options.size ? '?' + options.toString() : '');
  }
  if (legacy[pathname]) return STAGE_BASE + legacy[pathname];
  const page = pathname === '/index.php' ? (u.searchParams.get('page') || '') : pathname.slice(1);
  if (pathname === '/' || pathname === '/index.php' || publicPages.has(page)) {
    const query = new URLSearchParams();
    if (page && pathname !== '/') query.set('page', page);
    if (page === 'calculator' || page === 'lending') {
      for (const [key, value] of calculatorOptions(u.searchParams)) query.set(key, value);
    }
    return STAGE_BASE + '/index.php' + (query.size ? '?' + query.toString() : '') + u.hash;
  }
  return STAGE_BASE + pathname + u.search + u.hash;
}
