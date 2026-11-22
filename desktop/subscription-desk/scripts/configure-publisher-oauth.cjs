'use strict';
// Run during packaging. The values are OAuth *public* client IDs only.
const fs=require('node:fs');
const path=require('node:path');
const google=(process.env.SUBSCRIPTION_TRACKER_GOOGLE_CLIENT_ID||'').trim();
const microsoft=(process.env.SUBSCRIPTION_TRACKER_MICROSOFT_CLIENT_ID||'').trim();
const googleDesktopClientSecret=(process.env.SUBSCRIPTION_TRACKER_GOOGLE_DESKTOP_CLIENT_SECRET||'').trim();
const file=path.join(__dirname,'../src/config/publisher-oauth.json');
const data={googleClientId:google,googleDesktopClientSecret,microsoftClientId:microsoft};
fs.writeFileSync(file,JSON.stringify(data,null,2)+'\n');
require('../src/core/publisher-oauth.cjs').readPublisherOAuth(file);
console.log('Publisher OAuth configuration prepared: Google '+(google?'configured':'not configured')+', Microsoft '+(microsoft?'registered (integration pending)':'not configured')+'.');
