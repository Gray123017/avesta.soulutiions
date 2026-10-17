'use strict';

// Fixed provider destinations, never payment links scraped from an email.
const providers = [
  {key:'render', name:'Render', domains:['render.com'], match:/\brender\b/i, url:'https://dashboard.render.com/billing', help:'https://render.com/docs/billing', category:'Cloud', cancel:'Review each running service and its dependencies in Render. Deleting a service can destroy data; export or back up first.'},
  {key:'claude', name:'Claude', domains:['anthropic.com','claude.ai'], match:/\bclaude\b/i, url:'https://claude.ai/settings/billing', help:'https://support.claude.com/en/articles/8325617-how-do-i-cancel-my-paid-claude-subscription', category:'AI', cancel:'Open Billing and review the paid plan cancellation option. App Store or Google Play purchases must be managed with that store.'},
  {key:'ask-ai', name:'Ask AI Web', domains:['ask-ai.app','askai.app'], match:/\bask ai(?: web)?\b/i, url:null, help:'https://www.paddle.net/', category:'AI', cancel:'Use the subscription management link in your original receipt, or Paddle buyer support. Newsletter unsubscribe does not cancel the paid plan.'},
  {key:'chatgpt', name:'ChatGPT', domains:['openai.com'], match:/\bchatgpt\b/i, url:'https://chatgpt.com/', help:'https://help.openai.com/en/articles/7232927-how-do-i-cancel-my-chatgpt-subscription', category:'AI', cancel:'In ChatGPT, open Settings, Subscription, Manage. If purchased through a mobile store, cancel there.'},
  {key:'openai-api', name:'OpenAI API', domains:['openai.com'], match:/\b(?:api|credit top.?up)\b/i, url:'https://platform.openai.com/settings/organization/billing/overview', help:'https://help.openai.com/', category:'AI', cancel:'Review API billing, automatic recharge and active usage. API credits are separate from a ChatGPT subscription.'},
  {key:'hostinger', name:'Hostinger', domains:['hostinger.com'], url:'https://hpanel.hostinger.com/', help:'https://www.hostinger.com/support/', category:'Cloud', cancel:'In hPanel, review Billing and the exact hosting, email or domain subscription. Turning off auto-renew does not refund existing charges.'},
  {key:'replit', name:'Replit', domains:['replit.com'], url:'https://replit.com/account', help:'https://docs.replit.com/billing/managing-subscription', category:'Cloud', cancel:'Review the plan and usage-based services in account billing. Check deployments before changing your plan.'},
  {key:'lovable', name:'Lovable', domains:['lovable.dev'], url:'https://lovable.dev/settings/billing', help:'https://docs.lovable.dev/introduction/plans-and-credits', category:'AI', cancel:'Review the workspace plan, renewal and top-ups in Billing.'},
  {key:'intercom', name:'Intercom', domains:['intercom.io','intercom.com'], url:'https://app.intercom.com/', help:'https://www.intercom.com/help/', category:'Business', cancel:'Review the workspace subscription and billing settings. A trial-end email alone does not prove a paid conversion.'},
  {key:'google-one', name:'Google One', domains:['google.com'], match:/google one/i, url:'https://one.google.com/settings', help:'https://support.google.com/googleone/answer/9056360', category:'Storage', cancel:'Review membership in Google One settings. Check storage usage and what happens to backups before cancellation.'},
  {key:'google-workspace', name:'Google Workspace', domains:['google.com'], match:/google workspace|g suite/i, url:'https://admin.google.com/', help:'https://support.google.com/a/answer/1257646', category:'Business', cancel:'The workspace administrator can review Billing and Subscriptions. Check domain, mail and user dependencies first.'},
  {key:'google-play', name:'Google Play', domains:['google.com'], match:/google play/i, url:'https://play.google.com/store/account/subscriptions', help:'https://support.google.com/googleplay/answer/7018481', category:'Other', cancel:'Choose the exact paid app in Google Play subscriptions. A refund does not always establish that recurring billing was cancelled.'},
  {key:'monday', name:'monday.com', domains:['monday.com'], url:'https://monday.com/', help:'https://support.monday.com/', category:'Business', cancel:'Review the correct workspace billing plan. Trial extensions and upgrade invitations are not proof of payment.'},
  {key:'zapier', name:'Zapier', domains:['zapier.com'], url:'https://zapier.com/app/settings/billing', help:'https://help.zapier.com/', category:'Business', cancel:'Review the current plan, downgrade options and automation limits.'},
  {key:'netflix', name:'Netflix', domains:['netflix.com'], url:'https://www.netflix.com/YourAccount', help:'https://help.netflix.com/en/node/407', category:'Entertainment', cancel:'Confirm the correct member account and use the account cancellation flow. Partner-billed accounts may need the partner.'},
  {key:'spotify', name:'Spotify', domains:['spotify.com'], url:'https://www.spotify.com/account/overview/', help:'https://support.spotify.com/article/cancel-premium/', category:'Entertainment', cancel:'Review the current plan in your account. A mobile or partner-billed plan must be managed with that provider.'},
  {key:'adobe', name:'Adobe', domains:['adobe.com'], url:'https://account.adobe.com/plans', help:'https://helpx.adobe.com/manage-account/using/cancel-subscription.html', category:'Creative', cancel:'Review the cancellation quote and any commitment or early termination fee before submitting.'},
  {key:'microsoft', name:'Microsoft', domains:['microsoft.com'], url:'https://account.microsoft.com/services', help:'https://support.microsoft.com/account-billing', category:'Business', cancel:'Review the exact subscription and recurring billing setting in Services and subscriptions.'}
];
function getProvider(key) { return providers.find(p=>p.key===key); }
function byName(name='') { return providers.find(p=>name.toLowerCase().includes(p.name.toLowerCase())) || (name.toLowerCase().includes('openai api') ? getProvider('openai-api') : null); }
function senderDomain(from='') { const m=from.match(/<?[^\s<>@]+@([a-z0-9.-]+)>?/i); return m ? m[1].toLowerCase() : ''; }
function detectProvider(from, text) {
  const domain=senderDomain(from);
  const domainMatches=providers.filter(p=>p.domains.some(d=>domain===d || domain.endsWith('.'+d)));
  const exact=domainMatches.find(p=>!p.match || p.match.test(text));
  if(exact) return {...exact, identityReview:false};
  // Payment processors don't identify the actual subscription through their sender.
  const processor=/^(?:.*\.)?(?:paddle\.com|stripe\.com|paypal\.com|payproglobal\.com)$/.test(domain);
  if(processor) { const p=providers.find(p=>(p.match || new RegExp(p.name.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'i')).test(text)); if(p) return {...p,identityReview:true}; }
  return {key:'other:'+domain, name:domain || 'Unknown provider', domains:[domain], category:'Other', url:null, help:null, identityReview:true};
}
function safeHttps(value) { try {const u=new URL(value); return u.protocol==='https:' && !u.username && !u.password && !u.port && u.href.length<2048 ? u : null;}catch{return null;} }
function isSafeEvidenceUrl(value) { const u=safeHttps(value); return !!u && u.hostname==='mail.google.com'; }
function isKnownDestination(value) {const u=safeHttps(value); return !!u && providers.some(p=>[p.url,p.help].filter(Boolean).some(v=>new URL(v).hostname===u.hostname));}
module.exports={providers,getProvider,byName,senderDomain,detectProvider,safeHttps,isSafeEvidenceUrl,isKnownDestination};
