'use strict';
// OAuth public client IDs belong to the publisher's application registration.
// No client secret or user credentials must be embedded in an installer.
const fs = require('node:fs');
const path = require('node:path');
const GOOGLE_ID = /^[A-Za-z0-9._-]+\.apps\.googleusercontent\.com$/;
const MS_ID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
function readPublisherOAuth(file=path.join(__dirname,'../config/publisher-oauth.json')){
  let data={};
  try {data=JSON.parse(fs.readFileSync(file,'utf8'));} catch(e) {if(e.code!=='ENOENT')throw e;}
  const googleId=typeof data.googleClientId==='string'?data.googleClientId.trim():'';
  const microsoftId=typeof data.microsoftClientId==='string'?data.microsoftClientId.trim():'';
  if(googleId && !GOOGLE_ID.test(googleId))throw new Error('Publisher Google client ID is malformed.');
  if(microsoftId && !MS_ID.test(microsoftId))throw new Error('Publisher Microsoft client ID is malformed.');
  return Object.freeze({googleClientId:googleId,microsoftClientId:microsoftId});
}
module.exports={readPublisherOAuth};
