/** A separate Auth0 Login Action for this API. Keep existing Actions attached. */
exports.onExecutePostLogin = async (event, api) => {
  const resource = 'https://operations.mrnwebdesigns.com/mcp';
  if (event.resource_server?.identifier !== resource) return;
  // The connection that authenticated this session is authoritative. A linked
  // Google identity or a caller-provided email is not proof of Google login.
  const google = /^google-oauth2\|([0-9]{1,64})$/.exec(event.user?.user_id || '');
  if (event.connection?.strategy !== 'google-oauth2' || !/^con_[A-Za-z0-9]+$/.test(event.connection?.id || '')
    || !google || event.user?.email_verified !== true || typeof event.user.email !== 'string'
    || !/^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+@mrnwebdesigns\.com$/i.test(event.user.email)) {
    api.access.deny('Use an active MRN Google Workspace account.');
    return;
  }
  api.accessToken.setCustomClaim(`${resource}/email`, event.user.email.toLowerCase());
  api.accessToken.setCustomClaim(`${resource}/email_verified`, true);
  api.accessToken.setCustomClaim(`${resource}/identity_provider`, 'google-oauth2');
  api.accessToken.setCustomClaim(`${resource}/google_sub`, google[1]);
  api.accessToken.setCustomClaim(`${resource}/google_connection`, event.connection.id);
};
