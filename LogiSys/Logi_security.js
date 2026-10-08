(() => {
  const token = document.querySelector('meta[name="logisys-csrf"]')?.content || '';
  if (!token) return;

  const unsafe = method => ['POST', 'PUT', 'PATCH', 'DELETE'].includes(String(method || 'GET').toUpperCase());
  const sameOrigin = value => {
    try { return new URL(value, window.location.href).origin === window.location.origin; }
    catch { return false; }
  };

  const originalFetch = window.fetch.bind(window);
  window.fetch = (input, options = {}) => {
    const url = input instanceof Request ? input.url : String(input);
    const method = options.method || (input instanceof Request ? input.method : 'GET');
    if (!unsafe(method) || !sameOrigin(url)) return originalFetch(input, options);
    const headers = new Headers(input instanceof Request ? input.headers : undefined);
    new Headers(options.headers || {}).forEach((value, key) => headers.set(key, value));
    headers.set('X-CSRF-Token', token);
    return originalFetch(input, { ...options, headers });
  };

  const originalOpen = XMLHttpRequest.prototype.open;
  const originalSend = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function(method, url, ...rest) {
    this.__logisysCsrf = unsafe(method) && sameOrigin(url);
    return originalOpen.call(this, method, url, ...rest);
  };
  XMLHttpRequest.prototype.send = function(body) {
    if (this.__logisysCsrf) this.setRequestHeader('X-CSRF-Token', token);
    return originalSend.call(this, body);
  };

  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !unsafe(form.method) || !sameOrigin(form.action)) return;
    let field = form.querySelector('input[name="csrf_token"]');
    if (!field) {
      field = document.createElement('input');
      field.type = 'hidden';
      field.name = 'csrf_token';
      form.appendChild(field);
    }
    field.value = token;
  }, true);
})();

