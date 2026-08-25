// Comentarios: el servidor devuelve el HTML ya renderizado (comments.latte);
// aquí solo se pide y se inyecta. Las URLs vienen en los href/data-url del
// propio marcado, así que no hay que reconstruir rutas en JS.
document.addEventListener('DOMContentLoaded', function () {
	var box = document.getElementById('comments');
	var toggle = document.getElementById('comments-toggle');
	if (!box || !toggle) return;

	var loaded = false;

	function load(url) {
		box.innerHTML = '<p class="has-text-centered">Cargando…</p>';
		fetch(url)
			.then(function (r) { return r.text(); })
			.then(function (html) { box.innerHTML = html; })
			.catch(function () {
				box.innerHTML = '<p class="has-text-danger has-text-centered">No se pudieron cargar los comentarios.</p>';
			});
	}

	toggle.addEventListener('click', function (e) {
		e.preventDefault();
		if (loaded) { box.classList.toggle('is-hidden'); return; }
		loaded = true;
		load(box.dataset.url);
	});

	box.addEventListener('click', function (e) {
		var page = e.target.closest('a[data-page]');
		if (page) { e.preventDefault(); load(page.href); return; }

		var rep = e.target.closest('a[data-replies]');
		if (rep) {
			e.preventDefault();
			var target = document.getElementById('replies-' + rep.dataset.replies);
			if (!target) return;
			rep.textContent = 'Cargando…';
			fetch(rep.href)
				.then(function (r) { return r.text(); })
				.then(function (html) { target.innerHTML = html; })
				.catch(function () { rep.textContent = 'Error al cargar las respuestas'; });
		}
	});
});