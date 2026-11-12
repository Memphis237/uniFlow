try {
  localStorage.removeItem('uniflow_token');
} catch {
  // Storage can be disabled by the browser; authentication uses the HttpOnly cookie.
}
