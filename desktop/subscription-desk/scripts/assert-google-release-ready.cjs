'use strict';
// Packaging gate: do not publish a downloadable installer until the publisher
// has registered Google OAuth and explicitly confirmed a LIVE sign-in test.
// This attestation is not a substitute for the actual manual login test.
const {readPublisherOAuth}=require('../src/core/publisher-oauth.cjs');

function assertGoogleReleaseReady(oauthConfig,liveSignInApproved){
  if(!oauthConfig?.googleClientId){
    throw new Error(
      'RELEASE BLOCKED: Google OAuth is not configured. Publisher must register a Google Desktop OAuth client and set SUBSCRIPTION_TRACKER_GOOGLE_CLIENT_ID. Do not ask end users to create API credentials.'
    );
  }
  if(liveSignInApproved!=='true'){
    throw new Error(
      'RELEASE BLOCKED: Real Gmail sign-in and read-only subscription scan have not been approved as tested. After a successful live test, the publisher may set SUBSCRIPTION_TRACKER_GOOGLE_LIVE_TEST_APPROVED=true. This flag documents human verification; it does not run a live test.'
    );
  }
  return true;
}
if(require.main===module){
  try {
    assertGoogleReleaseReady(
      readPublisherOAuth(),
      process.env.SUBSCRIPTION_TRACKER_GOOGLE_LIVE_TEST_APPROVED
    );
    console.log('Google publisher configuration is present and a live sign-in test is attested. Packaging gate passed; verify other release requirements separately.');
  }catch(e){
    console.error(e.message);
    process.exitCode=1;
  }
}
module.exports={assertGoogleReleaseReady};
