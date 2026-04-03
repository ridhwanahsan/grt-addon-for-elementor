(function ($) {
	'use strict';

	const config = window.GRTTemplatesAdmin || {};
	const strings = config.strings || {};
	const $grid = $('#grt-template-grid');
	const $feedback = $('#grt-template-feedback');
	const $loadMore = $('#grt-load-more');
	const $spinner = $('#grt-library-spinner');
	const $empty = $('#grt-template-empty');

	if (!$grid.length) {
		return;
	}

	const state = {
		offset: 0,
		limit: Number(config.batchSize || 10),
		hasMore: true,
		loading: false,
		search: '',
		category: '',
		favoritesOnly: false,
		requestId: 0,
		templates: {}
	};

	const $search = $('#grt-template-search');
	const $category = $('#grt-category-filter');
	const $favoritesOnly = $('#grt-favorites-only');
	const $modal = $('#grt-preview-modal');
	const $frame = $('#grt-preview-frame');
	const $image = $('#grt-preview-image');
	const $previewTitle = $('#grt-preview-title');
	const $previewLink = $('#grt-preview-link');

	function escapeHtml(value) {
		return String(value || '').replace(/[&<>"']/g, function (match) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			}[match];
		});
	}

	function showFeedback(message, type, extraHtml) {
		const safeMessage = '<span>' + escapeHtml(message) + '</span>';
		$feedback
			.removeAttr('hidden')
			.removeClass('is-success is-error')
			.addClass(type === 'success' ? 'is-success' : 'is-error')
			.html(safeMessage + (extraHtml || ''));
	}

	function clearFeedback() {
		$feedback.attr('hidden', true).removeClass('is-success is-error').empty();
	}

	function setLoading(loading) {
		state.loading = loading;
		$spinner.prop('hidden', !loading);
	}

	function looksLikeImage(url) {
		return /\.(png|jpe?g|gif|svg|webp)(\?.*)?$/i.test(String(url || ''));
	}

	function renderRequiredMessage(template) {
		if (!template.missing_plugins || !template.missing_plugins.length) {
			return '';
		}

		const names = template.missing_plugins.map(function (plugin) {
			return escapeHtml(plugin.name);
		}).join(', ');

		return '<div class="grt-required">' + escapeHtml(strings.pluginsRequiredPrefix || 'Recommended plugins:') + ' ' + names + '</div>';
	}

	function renderCard(template) {
		const previewAvailable = template.preview_url || template.demo_url || template.thumbnail;
		const favoriteLabel = template.is_favorite ? (strings.unfavorite || 'Remove from favorites') : (strings.favorite || 'Favorite template');

		return [
			'<article class="grt-template-card" data-key="', escapeHtml(template.key), '">',
			'<div class="grt-card-media">',
			template.thumbnail ? '<img src="' + escapeHtml(template.thumbnail) + '" alt="' + escapeHtml(template.title) + '">' : '',
			'<button type="button" class="grt-card-favorite' + (template.is_favorite ? ' is-active' : '') + '" data-action="favorite" aria-label="' + escapeHtml(favoriteLabel) + '" title="' + escapeHtml(favoriteLabel) + '">',
			template.is_favorite ? '&#9733;' : '&#9734;',
			'</button>',
			'</div>',
			'<div class="grt-card-body">',
			'<div class="grt-card-heading">',
			'<div class="grt-card-badges">',
			'<span class="grt-badge is-category">' + escapeHtml(template.category || 'General') + '</span>',
			'<span class="grt-badge is-source">' + escapeHtml(template.source_label || template.source) + '</span>',
			'</div>',
			'<h2 class="grt-card-title">' + escapeHtml(template.title) + '</h2>',
			template.description ? '<p class="grt-card-description">' + escapeHtml(template.description) + '</p>' : '',
			'</div>',
			renderRequiredMessage(template),
			'<div class="grt-card-actions">',
			'<button type="button" class="button grt-button is-secondary" data-action="preview"' + (previewAvailable ? '' : ' disabled') + '>' + escapeHtml(strings.preview || 'Preview') + '</button>',
			'<button type="button" class="button grt-button is-primary" data-action="import">' + escapeHtml(strings.import || 'Import') + '</button>',
			'</div>',
			'</div>',
			'</article>'
		].join('');
	}

	function updateLoadMore() {
		if (state.hasMore && !state.loading) {
			$loadMore.removeAttr('hidden');
		} else {
			$loadMore.attr('hidden', true);
		}
	}

	function updateEmptyState() {
		$empty.prop('hidden', $grid.children().length !== 0);
	}

	function rememberTemplates(items) {
		items.forEach(function (template) {
			state.templates[template.key] = template;
		});
	}

	function loadTemplates(reset) {
		if (state.loading) {
			return;
		}

		if (reset) {
			state.offset = 0;
			state.hasMore = true;
			state.templates = {};
			$grid.empty();
			clearFeedback();
		}

		setLoading(true);
		updateLoadMore();
		state.requestId += 1;

		const currentRequestId = state.requestId;

		$.post(config.ajaxUrl, {
			action: 'grt_get_templates',
			nonce: config.nonce,
			offset: state.offset,
			limit: state.limit,
			search: state.search,
			category: state.category,
			favorites_only: state.favoritesOnly ? 1 : 0
		}).done(function (response) {
			if (currentRequestId !== state.requestId) {
				return;
			}

			if (!response || !response.success || !response.data) {
				showFeedback(strings.loadError || 'We could not load templates right now. Please try again.', 'error');
				return;
			}

			rememberTemplates(response.data.items || []);

			if (reset) {
				$grid.empty();
			}

			(response.data.items || []).forEach(function (template) {
				$grid.append(renderCard(template));
			});

			state.offset = Number(response.data.next_offset || 0);
			state.hasMore = !!response.data.has_more;
			updateLoadMore();
			updateEmptyState();
		}).fail(function (xhr) {
			const message = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : (strings.loadError || 'We could not load templates right now. Please try again.');
			showFeedback(message, 'error');
			updateEmptyState();
		}).always(function () {
			setLoading(false);
			updateLoadMore();
		});
	}

	function openPreview(template) {
		const previewUrl = template.preview_url || template.demo_url || template.thumbnail;

		if (!previewUrl) {
			showFeedback(strings.previewUnavailable || 'Preview is not available for this template yet.', 'error');
			return;
		}

		$previewTitle.text(template.title || strings.preview || 'Preview');
		$previewLink.attr('href', previewUrl).removeAttr('hidden');
		$frame.attr('hidden', true).attr('src', 'about:blank');
		$image.attr('hidden', true).attr('src', '').attr('alt', '');

		if (looksLikeImage(previewUrl)) {
			$image.attr('src', previewUrl).attr('alt', template.title || '').removeAttr('hidden');
		} else {
			$frame.attr('src', previewUrl).removeAttr('hidden');
		}

		$modal.removeAttr('hidden');
		$('body').addClass('grt-modal-open');
	}

	function closePreview() {
		$modal.attr('hidden', true);
		$frame.attr('src', 'about:blank');
		$image.attr('src', '').attr('alt', '');
		$('body').removeClass('grt-modal-open');
	}

	function setButtonLoading($button, loading, label) {
		if (loading) {
			$button.data('original-label', $button.text());
			$button.text(label || strings.importing || 'Importing...').addClass('is-loading').prop('disabled', true);
		} else {
			$button.text($button.data('original-label') || $button.text()).removeClass('is-loading').prop('disabled', false);
		}
	}

	function updateFavoriteButton($card, isFavorite) {
		const $button = $card.find('[data-action="favorite"]');
		const symbol = isFavorite ? '&#9733;' : '&#9734;';
		const label = isFavorite ? (strings.unfavorite || 'Remove from favorites') : (strings.favorite || 'Favorite template');

		$button.toggleClass('is-active', isFavorite).html(symbol).attr('aria-label', label).attr('title', label);
	}

	function importTemplate(template, $button) {
		if (template.missing_plugins && template.missing_plugins.length && !window.confirm(strings.missingPluginsConfirm || 'This template recommends plugins that are not active yet. Continue with the import anyway?')) {
			return;
		}

		setButtonLoading($button, true, strings.importing || 'Importing...');
		clearFeedback();

		$.post(config.ajaxUrl, {
			action: 'grt_import_template',
			nonce: config.nonce,
			template_key: template.key,
			force: template.missing_plugins && template.missing_plugins.length ? 1 : 0
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				showFeedback(strings.importError || 'The template could not be imported.', 'error');
				return;
			}

			let extraHtml = '';

			if (response.data.edit_url) {
				extraHtml = ' <a href="' + escapeHtml(response.data.edit_url) + '">' + escapeHtml(strings.openInElementor || 'Open in Elementor') + '</a>';
			}

			showFeedback(response.data.message || 'Template imported successfully.', 'success', extraHtml);
		}).fail(function (xhr) {
			const message = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : (strings.importError || 'The template could not be imported.');
			showFeedback(message, 'error');
		}).always(function () {
			setButtonLoading($button, false);
		});
	}

	function toggleFavorite(template, $card) {
		$.post(config.ajaxUrl, {
			action: 'grt_toggle_favorite',
			nonce: config.nonce,
			template_key: template.key
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				return;
			}

			template.is_favorite = !!response.data.is_favorite;
			state.templates[template.key] = template;
			updateFavoriteButton($card, template.is_favorite);

			if (state.favoritesOnly && !template.is_favorite) {
				loadTemplates(true);
			}
		});
	}

	let searchTimer = null;

	$search.on('input', function () {
		window.clearTimeout(searchTimer);
		searchTimer = window.setTimeout(function () {
			state.search = $search.val();
			loadTemplates(true);
		}, 250);
	});

	$category.on('change', function () {
		state.category = $category.val();
		loadTemplates(true);
	});

	$favoritesOnly.on('change', function () {
		state.favoritesOnly = $favoritesOnly.is(':checked');
		loadTemplates(true);
	});

	$loadMore.on('click', function () {
		loadTemplates(false);
	});

	$grid.on('click', '[data-action]', function () {
		const $button = $(this);
		const action = $button.data('action');
		const $card = $button.closest('.grt-template-card');
		const key = $card.data('key');
		const template = state.templates[key];

		if (!template) {
			return;
		}

		if (action === 'preview') {
			openPreview(template);
		}

		if (action === 'import') {
			importTemplate(template, $button);
		}

		if (action === 'favorite') {
			toggleFavorite(template, $card);
		}
	});

	$('#grt-preview-close').on('click', closePreview);

	$modal.on('click', function (event) {
		if ($(event.target).is('#grt-preview-modal')) {
			closePreview();
		}
	});

	$(document).on('keyup', function (event) {
		if (event.key === 'Escape' && !$modal.is('[hidden]')) {
			closePreview();
		}
	});

	loadTemplates(true);
})(jQuery);
