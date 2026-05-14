(function () {
  if (window.__devReloadAttached) return;
  window.__devReloadAttached = true;

  var topic = 'dev/reload';
  var url = '/.well-known/mercure?topic=' + encodeURIComponent(topic);
  var es;

  function connect() {
    es = new EventSource(url);
    es.onmessage = function (e) {
      try {
        var msg = JSON.parse(e.data);
        console.info('[dev-reload]', msg);
      } catch (_) {}
      location.reload();
    };
    es.onerror = function () {
      es.close();
      setTimeout(connect, 1000);
    };
  }
  connect();
})();
