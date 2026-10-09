/** A separate Auth0 Login Action for this API. Keep existing Actions attached. */
exports.onExecutePostLogin = async (event, api) => {
  const resource = 'https://operations.mrnwebdesigns.com/mcp';
  if (event.resource_server?.identifier !== resource) return;
  if (typeof event.user?.email !== 'string' || event.user.email_verified !== true) return;
  api.accessToken.setCustomClaim(`${resource}/email`, event.user.email.toLowerCase());
  api.accessToken.setCustomClaim(`${resource}/email_verified`, true);
};
