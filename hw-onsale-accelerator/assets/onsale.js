/**
 * HW On Sale - Luxury Fashion Edition
 * Lightweight JavaScript for carousel, load more, and analytics
 * 
 * Features:
 * - Smooth drag/swipe carousel with momentum
 * - Keyboard navigation support
 * - AJAX load more with scroll preservation
 * - Intersection Observer for impressions
 * - No external dependencies
 */

(function() {
	'use strict';

	// Session tracking
	const sessionId = getOrCreateSessionId();
	let hasTrackedView = false;
	const trackedImpressions = new Set();

	// Pagination state
	let currentOffset = 0;
        let currentPage = 1;
	let isLoading = false;
        let hasMore = true;
        const rupiahFormatter = new Intl.NumberFormat('id-ID');
        let titleClampTimer = null;
        const productOptionsCache = new Map();
        let productModal = null;
        let modalElements = null;
        let modalFocusReturn = null;
        let modalImageRequest = 0;
        let activePriceInput = null;

	/**
	 * Initialize on DOM ready
	 */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

        function init() {
                applyGridStyles();
                initializeHeaderOffset();
                initializeProductModal();
                initializeCards();
                initializeSliders();
                initializeTracking();
                initializeLoadMore();
                initializeFilters();
                initializePriceSlider();
                applyTitleClamp();
                scheduleTitleClamp();
                window.addEventListener('resize', scheduleTitleClamp);
        }

	/**
	 * Apply grid CSS variables from settings
	 */
	function applyGridStyles() {
		const grid = document.querySelector('.hw-onsale-grid');
		if (!grid || !window.hwOnsaleAcc) return;

		const settings = window.hwOnsaleAcc.settings || {};
		const columns = settings.gridColumns || {};
		
		grid.style.setProperty('--hw-onsale-desktop', columns.desktop || 4);
		grid.style.setProperty('--hw-onsale-tablet', columns.tablet || 3);
		grid.style.setProperty('--hw-onsale-mobile', columns.mobile || 2);
	}

	/**
	 * E) Calculate and apply header offset
	 */
        function initializeHeaderOffset() {
                function setHeaderOffset() {
                        const header = document.querySelector('.site-header, header, [role="banner"]');
                        if (header) {
                                const headerHeight = header.offsetHeight || 80;
                                document.documentElement.style.setProperty('--header-safe', headerHeight + 'px');
                        }
                }

                setHeaderOffset();
                window.addEventListener('resize', setHeaderOffset);
        }

        function initializeProductModal() {
                productModal = document.querySelector('.hw-product-modal');
                if (!productModal) return;

               modalElements = {
                       panel: productModal.querySelector('.hw-product-modal__panel'),
                       content: productModal.querySelector('.hw-product-modal__content'),
                       title: productModal.querySelector('[data-modal-title]'),
                       price: productModal.querySelector('[data-modal-price]'),
                       form: productModal.querySelector('[data-modal-form]'),
                       feedback: productModal.querySelector('[data-modal-feedback]'),
                       submit: productModal.querySelector('[data-modal-submit]'),
                       checkout: productModal.querySelector('[data-modal-checkout]'),
                       close: productModal.querySelector('[data-modal-close]'),
                       image: productModal.querySelector('[data-modal-image]'),
                       categories: productModal.querySelector('[data-modal-categories]'),
                       categoryList: productModal.querySelector('[data-modal-category-list]'),
                       size: productModal.querySelector('[data-modal-size]'),
                       sizeValue: productModal.querySelector('[data-modal-size-value]'),
                       viewDetails: productModal.querySelector('[data-modal-view-details]'),
                       imageWrapper: productModal.querySelector('[data-modal-image-trigger]')
               };

                if (modalElements.close) {
                        modalElements.close.addEventListener('click', closeProductModal);
                }

                if (modalElements.submit) {
                        modalElements.submit.addEventListener('click', function() {
                                const targetUrl = modalElements.submit.dataset.addToCart;
                                if (!targetUrl) return;

                                const productId = parseInt(productModal.dataset.productId || '0', 10);
                                const discountPct = parseInt(productModal.dataset.discount || '0', 10) || '';

                                if (productId) {
                                        trackEvent('add_to_cart', {
                                                product_id: productId,
                                                discount_pct: discountPct
                                        });
                                }

                                // Show success toast
                                showToast('Product successfully added to your cart!', 'success');

                                // Redirect after short delay to allow toast to display
                                setTimeout(function() {
                                        window.location.href = targetUrl;
                                }, 300);
                        });
                }

               if (modalElements.checkout) {
                       modalElements.checkout.addEventListener('click', function() {
                               const checkoutUrl = modalElements.checkout.dataset.checkout;
                               if (!checkoutUrl) return;

                               const productId = parseInt(productModal.dataset.productId || '0', 10);
                               const discountPct = parseInt(productModal.dataset.discount || '0', 10) || '';

                               if (productId) {
                                       trackEvent('direct_checkout', {
                                               product_id: productId,
                                               discount_pct: discountPct
                                       });
                               }

                               showToast('Taking you to checkout...', 'success');

                               setTimeout(function() {
                                       window.location.href = checkoutUrl;
                               }, 200);
                       });
               }

               if (modalElements.sizeValue) {
                       modalElements.sizeValue.addEventListener('click', handleSizeClick);
               }

               if (modalElements.viewDetails) {
                       modalElements.viewDetails.addEventListener('click', function(event) {
                               event.preventDefault();
                               navigateToProductPage();
                       });
               }

               if (modalElements.imageWrapper) {
                       modalElements.imageWrapper.addEventListener('click', function(event) {
                               if (event.target && event.target.closest('button, a')) {
                                       return;
                               }

                               event.preventDefault();
                               navigateToProductPage();
                       });

                       modalElements.imageWrapper.addEventListener('keydown', function(event) {
                               if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                                       event.preventDefault();
                                       navigateToProductPage();
                               }
                       });
               }

               productModal.addEventListener('click', function(event) {
                       if (event.target === productModal) {
                               closeProductModal();
                       }
                });
        }

        /**
         * Initialize product cards
         */
        function initializeCards() {
                const cards = document.querySelectorAll('.hw-onsale-card');

                cards.forEach(function(card) {
                        if (card.dataset.enhanced === '1') {
                                return;
                        }
                        card.dataset.enhanced = '1';

                        // Track card clicks excluding slider arrows
                        card.addEventListener('click', function(e) {
                                if (e.target.closest('.hw-onsale-slider__arrow')) {
                                        return;
                                }

                                trackEvent('card_click', {
                                        product_id: parseInt(card.dataset.productId, 10),
                                        discount_pct: parseInt(card.dataset.discount, 10)
                                });
                        });

                        const slider = card.querySelector('.hw-onsale-slider');
                        if (slider) {
                                slider.addEventListener('click', function(e) {
                                        if (slider.dataset.dragging === '1') {
                                                slider.dataset.dragging = '0';
                                                return;
                                        }

                                        if (e.target.closest('.hw-onsale-slider__arrow')) {
                                                return;
                                        }

                                        const anchor = e.target.closest('a');
                                        if (anchor) {
                                                e.preventDefault();
                                        }

                                        openProductModal(card);
                                });
                        }

                        const titleLink = card.querySelector('.hw-onsale-card__title a');
                        if (titleLink) {
                                titleLink.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        openProductModal(card);
                                });
                        }

                        const priceWrap = card.querySelector('.hw-pricewrap');
                        if (priceWrap) {
                                priceWrap.addEventListener('click', function(e) {
                                        if (e.target.closest('a, button')) {
                                                return;
                                        }

                                        e.preventDefault();
                                        openProductModal(card);
                                });

                                priceWrap.addEventListener('keydown', function(e) {
                                        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                                                e.preventDefault();
                                                openProductModal(card);
                                        }
                                });
                        }
                });
        }

       function navigateToProductPage() {
               if (!productModal) return;

               const productLink = productModal.dataset.productLink;
               if (!productLink) return;

               const productId = parseInt(productModal.dataset.productId || '0', 10);
               const discountPct = parseInt(productModal.dataset.discount || '0', 10) || '';

               if (productId) {
                       trackEvent('view_details', {
                               product_id: productId,
                               discount_pct: discountPct
                       });
               }

               window.location.href = productLink;
       }

       function openProductModal(card) {
                if (!productModal || !modalElements) return;

                const productId = parseInt(card.dataset.productId || '0', 10);
                if (!productId) return;

               modalImageRequest = 0;

               const productLink = card.dataset.productLink || card.querySelector('.hw-onsale-card__title a')?.href || '';

               productModal.dataset.productId = productId;
               productModal.dataset.discount = card.dataset.discount || '';
               productModal.dataset.productLink = productLink || '';

               modalFocusReturn = document.activeElement;

               const fallbackName = card.querySelector('.hw-onsale-card__title a')?.textContent?.trim() || '';
               const productFullName = typeof card.dataset.productName === 'string' && card.dataset.productName.trim()
                       ? card.dataset.productName.trim()
                       : fallbackName;
               const productName = truncateWords(productFullName, 4) || fallbackName;
               const priceHtml = card.querySelector('.hw-onsale-card__price')?.innerHTML || '';
                
                // Get the first product image
                const firstImage = card.querySelector('.hw-onsale-slider__slide img');
               if (modalElements.image) {
                       if (firstImage) {
                               const defaultAlt = firstImage.alt || productFullName || productName;
                               modalElements.image.src = firstImage.src;
                               modalElements.image.alt = defaultAlt;
                               modalElements.image.dataset.defaultSrc = firstImage.src;
                               modalElements.image.dataset.defaultAlt = defaultAlt;
                               modalElements.image.style.display = 'block';
                               modalElements.image.style.opacity = '1';
                       } else {
                               modalElements.image.removeAttribute('src');
                               modalElements.image.alt = productFullName || productName;
                               modalElements.image.dataset.defaultSrc = '';
                               modalElements.image.dataset.defaultAlt = productFullName || productName;
                               modalElements.image.style.display = 'none';
                               modalElements.image.style.opacity = '0';
                       }
               }

               setModalImageLoading(false);

               if (modalElements.title) {
                       modalElements.title.textContent = productName;
               }

               if (modalElements.price) {
                       modalElements.price.innerHTML = priceHtml;
               }

               if (modalElements.viewDetails) {
                       modalElements.viewDetails.disabled = !productLink;

                       if (modalElements.viewDetails.disabled) {
                               modalElements.viewDetails.setAttribute('aria-disabled', 'true');
                       } else {
                               modalElements.viewDetails.removeAttribute('aria-disabled');
                       }
               }

               if (modalElements.imageWrapper) {
                       const detailLabel = productFullName
                               ? 'View details for ' + productFullName
                               : 'View product details';
                       modalElements.imageWrapper.setAttribute('aria-label', detailLabel);
                       modalElements.imageWrapper.classList.toggle('is-clickable', Boolean(productLink));
                       modalElements.imageWrapper.tabIndex = productLink ? 0 : -1;

                       if (productLink) {
                               modalElements.imageWrapper.removeAttribute('aria-disabled');
                       } else {
                               modalElements.imageWrapper.setAttribute('aria-disabled', 'true');
                       }
               }

                const categoriesAttr = card.dataset.categories || card.dataset.materials || '[]';
                let categoriesData = [];
                try {
                        categoriesData = JSON.parse(categoriesAttr);
                        if (!Array.isArray(categoriesData)) {
                                categoriesData = [];
                        }
                } catch (error) {
                        categoriesData = [];
                }

                updateModalCategories(categoriesData);

                const rawSize = typeof card.dataset.size === 'string' ? card.dataset.size : '';
                updateModalSize(rawSize);

                if (modalElements.form) {
                        modalElements.form.innerHTML = '';
                }

                if (modalElements.feedback) {
                        modalElements.feedback.textContent = window.hwOnsaleAcc?.i18n?.loading || 'Loading...';
                }

                if (modalElements.submit) {
                        modalElements.submit.disabled = true;
                        delete modalElements.submit.dataset.addToCart;
                }

                if (modalElements.checkout) {
                        modalElements.checkout.disabled = true;
                        delete modalElements.checkout.dataset.checkout;
                }

                if (modalElements?.content) {
                        modalElements.content.scrollTop = 0;
                }

                if (modalElements?.panel) {
                        modalElements.panel.scrollTop = 0;
                }

                productModal.scrollTop = 0;

                productModal.setAttribute('aria-hidden', 'false');
                productModal.classList.add('is-open');
                document.body.classList.add('hw-modal-open');
                document.addEventListener('keydown', handleModalKeydown, true);

                if (modalElements.close) {
                        modalElements.close.focus();
                }

                if (modalElements?.content) {
                        modalElements.content.scrollTop = 0;
                        requestAnimationFrame(function() {
                                modalElements.content.scrollTop = 0;
                        });
                }

                fetchProductOptions(productId)
                        .then(function(data) {
                                renderProductOptions(data);
                        })
                        .catch(function() {
                                if (modalElements.feedback) {
                                        modalElements.feedback.textContent = window.hwOnsaleAcc?.i18n?.loadError || 'Unable to load options.';
                                }
                        });
        }

        function closeProductModal() {
                if (!productModal) return;

                modalImageRequest += 1;
                productModal.setAttribute('aria-hidden', 'true');
                productModal.classList.remove('is-open');
                document.body.classList.remove('hw-modal-open');
                document.removeEventListener('keydown', handleModalKeydown, true);

                if (modalElements?.form) {
                        modalElements.form.innerHTML = '';
                }

                if (modalElements?.feedback) {
                        modalElements.feedback.textContent = '';
                }

                if (modalElements?.submit) {
                        modalElements.submit.disabled = true;
                        delete modalElements.submit.dataset.addToCart;
                }

                if (modalElements?.checkout) {
                        modalElements.checkout.disabled = true;
                        delete modalElements.checkout.dataset.checkout;
                }

                updateModalCategories([]);
                updateModalSize('');

                if (modalFocusReturn && typeof modalFocusReturn.focus === 'function') {
                        modalFocusReturn.focus();
                }

                modalFocusReturn = null;
                delete productModal.dataset.productId;
                delete productModal.dataset.discount;
        }

        function updateModalSize(size) {
                if (!modalElements?.size || !modalElements?.sizeValue) {
                        return;
                }

                const wrapper = modalElements.size;
                const value = modalElements.sizeValue;
                const sizeText = typeof size === 'string' ? size.trim() : '';

                if (!sizeText) {
                        value.textContent = '';
                        value.disabled = true;
                        value.removeAttribute('data-size-search');
                        delete value.dataset.sizeSearch;
                        wrapper.hidden = true;
                        wrapper.setAttribute('aria-hidden', 'true');
                        return;
                }

                value.textContent = sizeText;
                const searchTerm = deriveSizeSearchTerm(sizeText);

                if (searchTerm) {
                        value.dataset.sizeSearch = searchTerm;
                        value.disabled = false;
                } else {
                        value.removeAttribute('data-size-search');
                        delete value.dataset.sizeSearch;
                        value.disabled = true;
                }

                wrapper.hidden = false;
                wrapper.removeAttribute('aria-hidden');
        }

        function handleSizeClick(event) {
                const target = event.currentTarget;

                if (!target || target.disabled) {
                        return;
                }

                const searchTerm = typeof target.dataset.sizeSearch === 'string'
                        ? target.dataset.sizeSearch.trim()
                        : '';

                if (!searchTerm) {
                        return;
                }

                const origin = typeof window.location.origin === 'string'
                        ? window.location.origin
                        : window.location.protocol + '//' + window.location.host;

                const normalizedOrigin = origin.endsWith('/') ? origin.slice(0, -1) : origin;
                const searchUrl = normalizedOrigin + '/?category=&s=' + encodeURIComponent(searchTerm) + '&post_type=product';
                const newWindow = window.open(searchUrl, '_blank', 'noopener,noreferrer');

                if (newWindow) {
                        newWindow.opener = null;
                }
        }

        function deriveSizeSearchTerm(sizeText) {
                if (typeof sizeText !== 'string') {
                        return '';
                }

                const normalized = sizeText.replace(/\s+/g, ' ').trim();

                if (!normalized) {
                        return '';
                }

                let measurement = normalized;

                if (/^size\b/i.test(measurement)) {
                        measurement = measurement.replace(/^size\s*/i, '');
                }

                measurement = measurement.replace(/cm$/i, '').trim();
                measurement = measurement.replace(/\bcm\b/gi, '').trim();

                if (!measurement) {
                        return '';
                }

                return measurement.replace(/\s{2,}/g, ' ');
        }

        function updateModalCategories(categories) {
                if (!modalElements?.categories || !modalElements?.categoryList) {
                        return;
                }

                const wrapper = modalElements.categories;
                const list = modalElements.categoryList;

                list.innerHTML = '';

                if (!Array.isArray(categories) || categories.length === 0) {
                        wrapper.hidden = true;
                        wrapper.setAttribute('aria-hidden', 'true');
                        return;
                }

                categories.forEach(function(category) {
                        if (!category || typeof category.name !== 'string' || !category.name.trim()) {
                                return;
                        }

                        const hasLink = typeof category.link === 'string' && category.link;
                        const chip = document.createElement(hasLink ? 'a' : 'span');
                        chip.className = 'hw-product-modal__material-chip hw-product-modal__category-chip';
                        chip.textContent = category.name.trim();

                        if (hasLink) {
                                chip.href = category.link;
                                chip.target = '_blank';
                                chip.rel = 'noopener noreferrer';
                        }

                        list.appendChild(chip);
                });

                if (!list.childNodes.length) {
                        wrapper.hidden = true;
                        wrapper.setAttribute('aria-hidden', 'true');
                        return;
                }

                wrapper.hidden = false;
                wrapper.removeAttribute('aria-hidden');
        }

        function handleModalKeydown(event) {
                if (event.key === 'Escape') {
                        event.preventDefault();
                        closeProductModal();
                }
        }

        function fetchProductOptions(productId) {
                if (productOptionsCache.has(productId)) {
                        return Promise.resolve(productOptionsCache.get(productId));
                }

                if (!window.hwOnsaleAcc?.restUrl) {
                        return Promise.reject(new Error('Missing endpoint'));
                }

                const url = window.hwOnsaleAcc.restUrl.replace(/\/$/, '') + '/product/' + productId + '/options';

                return fetch(url, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                                'X-WP-Nonce': window.hwOnsaleAcc?.nonce || ''
                        }
                })
                        .then(function(response) {
                                if (!response.ok) {
                                        throw new Error('Request failed');
                                }
                                return response.json();
                        })
                        .then(function(data) {
                                productOptionsCache.set(productId, data);
                                return data;
                        });
        }

        function renderProductOptions(data) {
                if (!modalElements) return;

                const hasInStockVariations = data?.has_in_stock_variations !== false;

                if (modalElements.price && data?.base_price_html) {
                        modalElements.price.innerHTML = data.base_price_html;
                }

                if (modalElements.feedback) {
                        modalElements.feedback.textContent = '';
                }

                if (!Array.isArray(data?.attributes) || data.attributes.length === 0) {
                        if (modalElements.form) {
                                modalElements.form.innerHTML = '';
                        }

                        if (modalElements.submit && data?.simple_add_to_cart_url) {
                                modalElements.submit.disabled = false;
                                modalElements.submit.dataset.addToCart = data.simple_add_to_cart_url;
                        } else if (modalElements.submit) {
                                modalElements.submit.disabled = true;
                                delete modalElements.submit.dataset.addToCart;
                        }

                        if (modalElements.checkout && data?.simple_checkout_url) {
                                modalElements.checkout.disabled = false;
                                modalElements.checkout.dataset.checkout = data.simple_checkout_url;
                        } else if (modalElements.checkout) {
                                modalElements.checkout.disabled = true;
                                delete modalElements.checkout.dataset.checkout;
                        }

                        if (!data?.simple_add_to_cart_url && modalElements.feedback && !hasInStockVariations) {
                                modalElements.feedback.textContent = window.hwOnsaleAcc?.i18n?.outOfStock || 'Out of stock';
                        }

                        return;
                }

                if (modalElements.form) {
                        modalElements.form.innerHTML = '';
                }

                const selects = [];
                const variations = Array.isArray(data?.variations) ? data.variations : [];
                const optionImageMap = buildOptionImageMap(variations);

                data.attributes.forEach(function(attribute) {
                        if (!Array.isArray(attribute.options) || attribute.options.length === 0) {
                                return;
                        }

                        const field = document.createElement('div');
                        field.className = 'hw-product-modal__field hw-product-modal__field--select';

                       const label = document.createElement('label');
                       label.className = 'hw-product-modal__label';
                       const rawLabelText = (attribute.label || attribute.name || '').trim();
                       if (/^warna$/i.test(rawLabelText)) {
                               label.textContent = 'Available Variant :';
                       } else {
                               label.textContent = rawLabelText;
                       }

                       const select = document.createElement('select');
                       select.className = 'hw-product-modal__select';
                        const normalizedSlug = normalizeAttributeSlug(attribute.slug || '');
                        const attributeSlug = normalizedSlug || (attribute.slug || '');
                        select.dataset.attribute = attributeSlug;

                       const placeholder = document.createElement('option');
                       placeholder.value = '';
                       let placeholderText = (attribute.placeholder || window.hwOnsaleAcc?.i18n?.chooseOption || 'Choose an option');
                       if (typeof placeholderText === 'string') {
                               placeholderText = placeholderText.trim() || placeholderText;
                       }

                       if ((typeof placeholderText === 'string' && /warna/i.test(placeholderText)) || /^warna$/i.test(rawLabelText)) {
                               placeholderText = 'choose variant';
                       }
                       placeholder.textContent = placeholderText;
                       select.appendChild(placeholder);

                        if (Array.isArray(attribute.options)) {
                                attribute.options.forEach(function(option) {
                                        const optionEl = document.createElement('option');
                                        optionEl.value = option.value;
                                        optionEl.textContent = option.label;
                                        const optionKey = createOptionKey(normalizedSlug || attributeSlug, option.value);
                                        const optionImage = optionImageMap.get(optionKey);
                                        if (optionImage && optionImage.src) {
                                                optionEl.dataset.image = optionImage.src;
                                        }
                                        if (optionImage && optionImage.alt) {
                                                optionEl.dataset.imageAlt = optionImage.alt;
                                        }
                                        select.appendChild(optionEl);
                                });
                        }

                        if (attribute.default) {
                                select.value = attribute.default;
                        }

                        select.addEventListener('change', handleChange);

                        field.appendChild(label);
                        field.appendChild(select);

                        if (modalElements.form) {
                                modalElements.form.appendChild(field);
                        }

                        selects.push(select);
                });

                if (!selects.length) {
                        if (modalElements.price && data?.base_price_html) {
                                modalElements.price.innerHTML = data.base_price_html;
                        }

                        if (modalElements.submit) {
                                modalElements.submit.disabled = true;
                                delete modalElements.submit.dataset.addToCart;
                        }

                        if (modalElements.checkout) {
                                modalElements.checkout.disabled = true;
                                delete modalElements.checkout.dataset.checkout;
                        }

                        if (modalElements.feedback && !hasInStockVariations) {
                                modalElements.feedback.textContent = window.hwOnsaleAcc?.i18n?.outOfStock || 'Out of stock';
                        }

                        return;
                }

                handleChange();

                if (selects.length) {
                        const shouldAutoFocus = typeof window.matchMedia === 'function'
                                && window.matchMedia('(pointer: fine) and (hover: hover)').matches;
                        if (shouldAutoFocus) {
                                requestAnimationFrame(function() {
                                        selects[0].focus();
                                });
                        }
                }

                function handleChange() {
                        const selected = {};
                        let allSelected = true;
                        let selectedOptionImage = null;

                        selects.forEach(function(select) {
                                const attributeKey = normalizeAttributeSlug(select.dataset.attribute || '');
                                if (!select.value) {
                                        allSelected = false;
                                } else {
                                        if (!attributeKey) {
                                                allSelected = false;
                                                return;
                                        }
                                        selected[attributeKey] = select.value;
                                        const optionMatch = getOptionImage(optionImageMap, attributeKey, select.value, select);
                                        if (optionMatch) {
                                                selectedOptionImage = optionMatch;
                                        }
                                }
                        });

                        if (!allSelected) {
                                if (modalElements.price && data?.base_price_html) {
                                        modalElements.price.innerHTML = data.base_price_html;
                                }

                                if (modalElements.submit) {
                                        modalElements.submit.disabled = true;
                                        delete modalElements.submit.dataset.addToCart;
                                }

                                if (modalElements.checkout) {
                                        modalElements.checkout.disabled = true;
                                        delete modalElements.checkout.dataset.checkout;
                                }

                                if (modalElements.feedback) {
                                        modalElements.feedback.textContent = '';
                                }

                                if (selectedOptionImage) {
                                        updateModalImage(selectedOptionImage);
                                } else {
                                        updateModalImage(null);
                                }

                                return;
                        }

                        const matched = findMatchingVariation(variations, selected);

                        if (matched && matched.is_in_stock && matched.add_to_cart_url) {
                                if (modalElements.price && matched.price_html) {
                                        modalElements.price.innerHTML = matched.price_html;
                                }

                                if (modalElements.submit) {
                                        modalElements.submit.disabled = false;
                                        modalElements.submit.dataset.addToCart = matched.add_to_cart_url;
                                }

                                if (modalElements.checkout && matched.checkout_url) {
                                        modalElements.checkout.disabled = false;
                                        modalElements.checkout.dataset.checkout = matched.checkout_url;
                                } else if (modalElements.checkout) {
                                        modalElements.checkout.disabled = true;
                                        delete modalElements.checkout.dataset.checkout;
                                }

                                if (modalElements.feedback) {
                                        modalElements.feedback.textContent = '';
                                }

                                const matchedImage = normalizeImageData(matched.image);
                                if (matchedImage.src || selectedOptionImage) {
                                        updateModalImage(matchedImage.src ? matchedImage : selectedOptionImage);
                                } else {
                                        updateModalImage(null);
                                }
                        } else if (matched && !matched.is_in_stock) {
                                if (modalElements.price && matched.price_html) {
                                        modalElements.price.innerHTML = matched.price_html;
                                }

                                if (modalElements.submit) {
                                        modalElements.submit.disabled = true;
                                        delete modalElements.submit.dataset.addToCart;
                                }

                                if (modalElements.checkout) {
                                        modalElements.checkout.disabled = true;
                                        delete modalElements.checkout.dataset.checkout;
                                }

                                if (modalElements.feedback) {
                                        modalElements.feedback.textContent = window.hwOnsaleAcc?.i18n?.outOfStock || 'Out of stock';
                                }

                                const matchedImage = normalizeImageData(matched.image);
                                if (matchedImage.src || selectedOptionImage) {
                                        updateModalImage(matchedImage.src ? matchedImage : selectedOptionImage);
                                } else {
                                        updateModalImage(null);
                                }
                        } else {
                                if (modalElements.submit) {
                                        modalElements.submit.disabled = true;
                                        delete modalElements.submit.dataset.addToCart;
                                }

                                if (modalElements.checkout) {
                                        modalElements.checkout.disabled = true;
                                        delete modalElements.checkout.dataset.checkout;
                                }

                                if (modalElements.feedback) {
                                        modalElements.feedback.textContent = window.hwOnsaleAcc?.i18n?.unavailable || 'Combination unavailable';
                                }

                                if (selectedOptionImage) {
                                        updateModalImage(selectedOptionImage);
                                } else {
                                        updateModalImage(null);
                                }
                        }
                }
        }

        function findMatchingVariation(variations, selected) {
                if (!Array.isArray(variations)) return null;

                for (let i = 0; i < variations.length; i++) {
                        const variation = variations[i];
                        if (!variation || !variation.attributes) continue;

                        const variationAttributes = variation.attributes;
                        const isMatch = Object.keys(selected).every(function(key) {
                                const normalizedKey = normalizeAttributeSlug(key);
                                const variationValue = variationAttributes[normalizedKey] !== undefined
                                        ? variationAttributes[normalizedKey]
                                        : variationAttributes[key];

                                return String(variationValue) === String(selected[key]);
                        });

                        if (isMatch) {
                                return variation;
                        }
                }

                return null;
        }

        function normalizeAttributeSlug(slug) {
                if (typeof slug !== 'string') {
                        return '';
                }

                let normalized = slug.trim();

                if (normalized.startsWith('attribute_')) {
                        normalized = normalized.slice('attribute_'.length);
                }

                if (!normalized) {
                        return '';
                }

                return 'attribute_' + normalized.replace(/^_+/, '');
        }

        function createOptionKey(attribute, value) {
                return attribute + '::' + value;
        }

        function buildOptionImageMap(variations) {
                const map = new Map();

                if (!Array.isArray(variations)) {
                        return map;
                }

                variations.forEach(function(variation) {
                        if (!variation || !variation.attributes || variation.is_in_stock === false) return;

                        const normalizedImage = normalizeImageData(variation.image);
                        if (!normalizedImage.src) return;

                        Object.keys(variation.attributes).forEach(function(key) {
                                const value = variation.attributes[key];
                                if (!value) return;

                                const normalizedKey = normalizeAttributeSlug(key);
                                if (!normalizedKey) return;

                                const optionKey = createOptionKey(normalizedKey, value);
                                if (!map.has(optionKey)) {
                                        map.set(optionKey, normalizedImage);
                                }
                        });
                });

                return map;
        }

        function getOptionImage(optionImageMap, attributeKey, value, select) {
                if (!attributeKey || !value) {
                        return null;
                }

                const optionKey = createOptionKey(attributeKey, value);
                const mappedImage = optionImageMap.get(optionKey);
                if (mappedImage && mappedImage.src) {
                        return mappedImage;
                }

                if (select && typeof select.selectedIndex === 'number' && select.selectedIndex >= 0) {
                        const selectedOption = select.options[select.selectedIndex];
                        if (selectedOption) {
                                const optionImage = normalizeImageData({
                                        src: selectedOption.dataset?.image,
                                        alt: selectedOption.dataset?.imageAlt
                                });

                                if (optionImage.src) {
                                        return optionImage;
                                }
                        }
                }

                return null;
        }

        function normalizeImageData(image) {
                if (!image) {
                        return { src: '', alt: '' };
                }

                if (typeof image === 'string') {
                        return { src: image, alt: '' };
                }

                if (typeof image === 'object') {
                        return {
                                src: image.src || '',
                                alt: image.alt || ''
                        };
                }

                return { src: '', alt: '' };
        }

        /**
         * Update modal image with fade transition
         */
       function setModalImageLoading(isLoading) {
               if (!modalElements?.imageWrapper) return;

               const wrapper = modalElements.imageWrapper;
               wrapper.classList.toggle('is-loading', Boolean(isLoading));

               if (isLoading) {
                       wrapper.setAttribute('aria-busy', 'true');
               } else {
                       wrapper.removeAttribute('aria-busy');
               }
       }

       function updateModalImage(imageData) {
               if (!modalElements?.image) return;

               const imageElement = modalElements.image;
               const fallback = normalizeImageData({
                       src: imageElement.dataset.defaultSrc,
                       alt: imageElement.dataset.defaultAlt || imageElement.alt || ''
               });
               const target = (function() {
                       const normalized = normalizeImageData(imageData);
                       if (normalized.src) {
                               return normalized;
                       }
                       return fallback;
               })();

               if (!target.src) {
                       imageElement.style.opacity = '0';
                       imageElement.style.display = 'none';
                       setModalImageLoading(false);
                       return;
               }

               if (imageElement.src === target.src) {
                       if (target.alt) {
                               imageElement.alt = target.alt;
                       }
                       imageElement.style.display = 'block';
                       imageElement.style.opacity = '1';
                       setModalImageLoading(false);
                       return;
               }

               const requestId = ++modalImageRequest;
               const loader = new Image();

               setModalImageLoading(true);

               loader.onload = function() {
                       if (requestId !== modalImageRequest) {
                               return;
                       }

                       imageElement.style.transition = 'opacity 200ms ease-in-out';
                       imageElement.style.opacity = '0';

                       setTimeout(function() {
                               if (requestId !== modalImageRequest) {
                                       return;
                               }

                               imageElement.src = target.src;
                               if (target.alt) {
                                       imageElement.alt = target.alt;
                               }
                               imageElement.style.display = 'block';

                               requestAnimationFrame(function() {
                                       if (requestId !== modalImageRequest) {
                                               return;
                                       }
                                       imageElement.style.opacity = '1';
                                       setModalImageLoading(false);
                               });
                       }, 120);
               };

               loader.onerror = function() {
                       if (requestId !== modalImageRequest) {
                               return;
                       }

                       if (fallback.src && fallback.src !== target.src) {
                               updateModalImage(fallback);
                       } else {
                               imageElement.style.opacity = '0';
                               imageElement.style.display = 'none';
                               setModalImageLoading(false);
                       }
               };

               loader.src = target.src;
       }

        /**
         * Initialize image sliders with drag/swipe and arrows
         */
        function initializeSliders() {
                const sliders = document.querySelectorAll('.hw-onsale-slider');

                sliders.forEach(function(slider) {
                        if (slider.dataset.enhanced === '1') {
                                return;
                        }
                        slider.dataset.enhanced = '1';

                        const track = slider.querySelector('.hw-onsale-slider__track');
                        const slides = slider.querySelectorAll('.hw-onsale-slider__slide');
                        const dotsContainer = slider.querySelector('.hw-onsale-slider__dots');
                        const dots = dotsContainer ? Array.from(dotsContainer.querySelectorAll('.hw-onsale-slider__dot')) : [];

                        if (slides.length <= 1) {
                                if (dotsContainer) {
                                        dotsContainer.hidden = true;
                                }
                                return;
                        }

                        const totalSlides = slides.length;
                        const dotCount = dots.length;
                        const dotTargets = dots.map(function(dot, dotIndex) {
                                const datasetValue = parseInt(dot.dataset.targetSlide || '', 10);
                                if (!Number.isNaN(datasetValue)) {
                                        const clamped = Math.max(0, Math.min(datasetValue, totalSlides - 1));
                                        dot.dataset.targetSlide = clamped.toString();
                                        return clamped;
                                }

                                const fallbackTarget = computeTargetSlide(dotIndex);
                                dot.dataset.targetSlide = fallbackTarget.toString();
                                return fallbackTarget;
                        });

                        // Add arrow navigation
                        const prevArrow = createArrow('prev');
                        const nextArrow = createArrow('next');
                        slider.appendChild(prevArrow);
                        slider.appendChild(nextArrow);

                        slider.dataset.dragging = slider.dataset.dragging || '0';

                        // Slider state
                        let currentSlide = 0;
                        let startX = 0;
                        let startY = 0;
			let currentX = 0;
			let isDragging = false;
			let hasMoved = false;
			let startTransform = 0;

                        if (dotsContainer && dotCount > 0) {
                                dotsContainer.hidden = false;
                                dots.forEach(function(dot, dotIndex) {
                                        dot.addEventListener('click', function(event) {
                                                event.preventDefault();
                                                event.stopPropagation();
                                                const target = typeof dotTargets[dotIndex] === 'number' ? dotTargets[dotIndex] : computeTargetSlide(dotIndex);
                                                goToSlide(target);
                                        });
                                });
                        }

                        // Touch/Mouse events
			track.addEventListener('mousedown', handleStart, { passive: false });
			track.addEventListener('touchstart', handleStart, { passive: false });
			document.addEventListener('mousemove', handleMove, { passive: false });
			document.addEventListener('touchmove', handleMove, { passive: false });
			document.addEventListener('mouseup', handleEnd);
			document.addEventListener('touchend', handleEnd);
			track.addEventListener('mouseleave', handleCancel);
			
			// Prevent image dragging
			track.addEventListener('dragstart', function(e) {
				e.preventDefault();
			});

                        function handleStart(e) {
                                isDragging = true;
                                hasMoved = false;

                                slider.dataset.dragging = '0';

                                const point = e.type === 'touchstart' ? e.touches[0] : e;
                                startX = point.clientX;
                                startY = point.clientY;
                                currentX = point.clientX;
				startTransform = currentSlide * -100;
				
				track.style.transition = 'none';
				track.style.cursor = 'grabbing';
			}

			function handleMove(e) {
				if (!isDragging) return;

				const point = e.type === 'touchmove' ? e.touches[0] : e;
				const deltaX = point.clientX - startX;
				const deltaY = point.clientY - startY;
				
				// Determine if horizontal swipe (not vertical scroll)
				if (!hasMoved && Math.abs(deltaX) > Math.abs(deltaY)) {
					hasMoved = true;
				}
				
                                        if (hasMoved) {
                                                e.preventDefault();
                                                currentX = point.clientX;

                                                slider.dataset.dragging = '1';

                                                const diff = currentX - startX;
                                                const percent = (diff / slider.offsetWidth) * 100;
                                                let newTransform = startTransform + percent;

					// Add resistance at edges
					const maxTransform = (slides.length - 1) * -100;
					if (newTransform > 0) {
						newTransform = newTransform * 0.3;
					} else if (newTransform < maxTransform) {
						newTransform = maxTransform + (newTransform - maxTransform) * 0.3;
					}
					
					track.style.transform = 'translateX(' + newTransform + '%)';
				}
			}

                        function handleEnd(e) {
                                if (!isDragging) return;
                                isDragging = false;

                                slider.dataset.dragging = '0';

                                const point = e.type === 'touchend' ? e.changedTouches[0] : e;
                                const diff = (point?.clientX || currentX) - startX;
                                const threshold = slider.offsetWidth * 0.2;

				track.style.transition = 'transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1)';

				// Determine new slide
				if (Math.abs(diff) > threshold) {
					if (diff > 0 && currentSlide > 0) {
						goToSlide(currentSlide - 1);
					} else if (diff < 0 && currentSlide < slides.length - 1) {
						goToSlide(currentSlide + 1);
					} else {
						goToSlide(currentSlide);
					}
				} else {
					goToSlide(currentSlide);
				}
			}

                        function handleCancel() {
                                if (isDragging) {
                                        isDragging = false;
                                        track.style.transition = 'transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1)';
                                        goToSlide(currentSlide);
                                }

                                slider.dataset.dragging = '0';
                        }

                        function goToSlide(index) {
                                currentSlide = Math.max(0, Math.min(index, slides.length - 1));
                                track.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';
                                updateDots();
                        }

                        function updateDots() {
                                if (!dotsContainer || dotCount === 0) {
                                        return;
                                }

                                const activeDot = getActiveDotIndex(currentSlide);

                                dots.forEach(function(dot, dotIndex) {
                                        const isActive = dotIndex === activeDot;
                                        dot.classList.toggle('is-active', isActive);
                                        dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
                                });
                        }

                        function getActiveDotIndex(slideIndex) {
                                if (dotCount === 0) {
                                        return -1;
                                }

                                if (totalSlides <= dotCount) {
                                        return Math.min(slideIndex, dotCount - 1);
                                }

                                if (dotTargets.length === 0) {
                                        return 0;
                                }

                                if (slideIndex <= dotTargets[0]) {
                                        return 0;
                                }

                                const lastIndex = dotTargets.length - 1;
                                if (slideIndex >= dotTargets[lastIndex]) {
                                        return lastIndex;
                                }

                                return Math.min(1, lastIndex);
                        }

                        function computeTargetSlide(dotIndex) {
                                if (totalSlides <= dotCount) {
                                        return Math.max(0, Math.min(dotIndex, totalSlides - 1));
                                }

                                if (dotIndex <= 0) {
                                        return 0;
                                }

                                if (dotIndex >= dotCount - 1) {
                                        return totalSlides - 1;
                                }

                                return Math.round((totalSlides - 1) / 2);
                        }

			// C) Arrow click handlers
                        prevArrow.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                if (currentSlide > 0) goToSlide(currentSlide - 1);
                        });

                        nextArrow.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                if (currentSlide < slides.length - 1) goToSlide(currentSlide + 1);
                        });

			// Keyboard navigation (left/right arrows)
                        slider.addEventListener('keydown', function(e) {
                                if (e.key === 'ArrowLeft' && currentSlide > 0) {
                                        e.preventDefault();
                                        goToSlide(currentSlide - 1);
                                } else if (e.key === 'ArrowRight' && currentSlide < slides.length - 1) {
                                        e.preventDefault();
                                        goToSlide(currentSlide + 1);
                                }
                        });

                        updateDots();
                });
        }

	/**
	 * C) Create arrow element
	 */
	function createArrow(direction) {
		const arrow = document.createElement('button');
		arrow.className = 'hw-onsale-slider__arrow hw-onsale-slider__arrow--' + direction;
		arrow.setAttribute('aria-label', direction === 'prev' ? 'Previous image' : 'Next image');
		arrow.innerHTML = direction === 'prev' ? '‹' : '›';
		return arrow;
	}

	/**
	 * Initialize impression and event tracking
	 */
	function initializeTracking() {
		if (!window.hwOnsaleAcc?.settings?.trackingEnabled) {
			return;
		}

		// Track page view once
		if (!hasTrackedView) {
			trackEvent('view');
			hasTrackedView = true;
		}

		// Track product impressions with Intersection Observer
		const observer = new IntersectionObserver(function(entries) {
			entries.forEach(function(entry) {
				if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
					const card = entry.target;
					const productId = parseInt(card.dataset.productId, 10);

					if (!trackedImpressions.has(productId)) {
						trackedImpressions.add(productId);
						trackEvent('impression', {
							product_id: productId,
							discount_pct: parseInt(card.dataset.discount, 10)
						});
					}
				}
			});
		}, { 
			threshold: 0.5,
			rootMargin: '0px'
		});

		document.querySelectorAll('.hw-onsale-card').forEach(function(card) {
			observer.observe(card);
		});
	}

	/**
	 * Initialize load more button
	 */
        function initializeLoadMore() {
                const button = document.querySelector('.hw-onsale-load-more__button');
                if (!button) return;

                const totalPages = window.hwOnsaleAcc?.initial?.totalPages || 1;
                const totalProducts = window.hwOnsaleAcc?.initial?.total || 0;
                if (totalPages <= 1 || totalProducts <= (window.hwOnsaleAcc?.settings?.batchSize || 12)) {
                        hasMore = false;
                        button.style.display = 'none';
                        return;
                }

                const label = button.querySelector('.hw-onsale-load-more__label');
                if (label) {
                        button.dataset.defaultLabel = label.textContent.trim();
                } else if (!button.dataset.defaultLabel) {
                        button.dataset.defaultLabel = button.textContent.trim();
                }

                button.addEventListener('click', loadMore);
        }

	/**
	 * Load more products via AJAX
	 */
	function loadMore() {
		if (isLoading || !hasMore) return;

		isLoading = true;
                const button = document.querySelector('.hw-onsale-load-more__button');
                const scrollY = window.scrollY;

                const label = button.querySelector('.hw-onsale-load-more__label');
                if (label && button.dataset.defaultLabel) {
                        label.textContent = button.dataset.defaultLabel;
                }

                button.classList.add('is-loading');
                button.disabled = true;

                const batchSize = window.hwOnsaleAcc?.settings?.batchSize || 12;
                currentPage += 1;
                currentOffset = (currentPage - 1) * batchSize;

                // Build URL with current filters from URL params
                const urlParams = new URLSearchParams(window.location.search);
                const params = new URLSearchParams();
                params.set('page', currentPage);
                params.set('per_page', batchSize);
		
		// Add filters if present
		if (urlParams.get('orderby')) params.set('orderby', urlParams.get('orderby'));
		if (urlParams.get('min_price')) params.set('min_price', urlParams.get('min_price'));
                if (urlParams.get('max_price')) params.set('max_price', urlParams.get('max_price'));
                if (urlParams.get('categories')) params.set('categories', urlParams.get('categories'));

                fetch(window.hwOnsaleAcc.restUrl + '/list?' + params.toString(), {
                        headers: {
                                'X-WP-Nonce': window.hwOnsaleAcc?.nonce || ''
                        }
                })
                        .then(function(response) {
                                if (!response.ok) throw new Error('Network response failed');
                                const total = parseInt(response.headers.get('X-Total-Count') || '0', 10);
                                const totalPages = parseInt(response.headers.get('X-Total-Pages') || '1', 10);
                                return response.text().then(function(html) {
                                        return { html, total, totalPages };
                                });
                        })
                        .then(function(data) {
                                if (data.html && data.html.trim().length > 0) {
                                        appendProductsFromHTML(data.html);

                                        // Restore scroll position
                                        window.scrollTo(0, scrollY);

                                        // Check if more products available
                                        if (currentPage >= data.totalPages || currentOffset + batchSize >= data.total) {
                                                hasMore = false;
                                                button.style.display = 'none';
                                        }

                                        trackEvent('load_more', {
                                                extra: {
                                                        offset: currentOffset,
                                                        count: batchSize
                                                }
                                        });
                                } else {
                                        hasMore = false;
                                        button.style.display = 'none';
				}
			})
                        .catch(function(error) {
                                console.error('Load more failed:', error);
                                currentPage = Math.max(1, currentPage - 1);
                                const batchSize = window.hwOnsaleAcc?.settings?.batchSize || 12;
                                currentOffset = (currentPage - 1) * batchSize;
                                const label = button.querySelector('.hw-onsale-load-more__label');
                                if (label) {
                                        label.textContent = 'Failed to load. Try again.';
                                } else {
                                        button.textContent = 'Failed to load. Try again.';
                                }
                        })
			.finally(function() {
				isLoading = false;
				button.classList.remove('is-loading');
				button.disabled = false;
			});
	}

	/**
	 * Append products to grid
	 */
        function appendProductsFromHTML(html) {
                if (!html) return;

                const grid = document.querySelector('.hw-onsale-grid');
                if (!grid) return;

                const template = document.createElement('template');
                template.innerHTML = html.trim();

                const fragment = document.createDocumentFragment();
                while (template.content.firstChild) {
                        fragment.appendChild(template.content.firstChild);
                }

                grid.appendChild(fragment);

                initializeCards();
                initializeSliders();
                initializeTracking();
                applyTitleClamp();
                scheduleTitleClamp();
        }

	/**
	 * Track event via beacon API
	 */
	function trackEvent(event, data) {
		if (!window.hwOnsaleAcc?.settings?.trackingEnabled) {
			return;
		}

		data = data || {};

		const payload = {
			session_id: sessionId,
			event: event,
			device: getDeviceType(),
			ref: document.referrer || '',
			product_id: data.product_id || '',
			discount_pct: data.discount_pct || '',
			extra: data.extra ? JSON.stringify(data.extra) : '',
			_wpnonce: window.hwOnsaleAcc?.nonce || ''
		};

		// Use sendBeacon for reliability
		if (navigator.sendBeacon) {
			navigator.sendBeacon(
				window.hwOnsaleAcc.restUrl + '/event',
				new URLSearchParams(payload)
			);
		}
	}

	/**
	 * Get or create session ID
	 */
	function getOrCreateSessionId() {
		const cookieName = 'hw_onsale_session';
		let sessionId = getCookie(cookieName);

		if (!sessionId) {
			sessionId = generateId();
			setCookie(cookieName, sessionId, 1);
		}

		return sessionId;
	}

	/**
	 * Generate unique ID
	 */
	function generateId() {
		return Date.now().toString(36) + Math.random().toString(36).substr(2, 9);
	}

	/**
	 * Get cookie value
	 */
	function getCookie(name) {
		const value = '; ' + document.cookie;
		const parts = value.split('; ' + name + '=');
		if (parts.length === 2) {
			return parts.pop().split(';').shift();
		}
		return null;
	}

	/**
	 * Set cookie
	 */
	function setCookie(name, value, days) {
		const expires = new Date(Date.now() + days * 86400000).toUTCString();
		document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax';
	}

	/**
	 * Get device type
	 */
	function getDeviceType() {
		const width = window.innerWidth;
		if (width < 768) return 'mobile';
		if (width < 992) return 'tablet';
		return 'desktop';
	}

        /**
         * Limit text to a specific number of words
         */
        function truncateWords(text, limit) {
                if (typeof text !== 'string') return '';

                const trimmed = text.trim();
                if (!trimmed) return '';

                const safeLimit = Number(limit);
                if (!Number.isFinite(safeLimit) || safeLimit <= 0) {
                        return '';
                }

                const words = trimmed.split(/\s+/);
                if (words.length <= safeLimit) {
                        return words.join(' ');
                }

                return words.slice(0, safeLimit).join(' ');
        }

	/**
	 * H) Initialize filters and sorting
	 */
        function initializeFilters() {
                const sortSelect = document.querySelector('.hw-sort-select');
                const filterBtn = document.querySelector('.hw-filter-btn');
                const filterDrawer = document.querySelector('.hw-filter-drawer');
                const closeDrawer = document.querySelector('.hw-filter-drawer__close');
                const applyBtn = document.querySelector('.hw-filter-apply');
                const resetButtons = document.querySelectorAll('.js-hw-filter-reset');

                // Sort change handler
                if (sortSelect) {
                        sortSelect.addEventListener('change', function() {
                                updateURLAndReload();
                        });
                }

		// Filter drawer toggle
		if (filterBtn && filterDrawer) {
			filterBtn.addEventListener('click', function() {
				filterDrawer.classList.add('is-open');
			});

			if (closeDrawer) {
				closeDrawer.addEventListener('click', function() {
					filterDrawer.classList.remove('is-open');
				});
			}

			// Close on backdrop click
			filterDrawer.addEventListener('click', function(e) {
				if (e.target === filterDrawer) {
					filterDrawer.classList.remove('is-open');
				}
			});
		}

		// Apply filters
		if (applyBtn) {
			applyBtn.addEventListener('click', function() {
				updateURLAndReload();
				if (filterDrawer) filterDrawer.classList.remove('is-open');
			});
		}

		// Reset filters
                if (resetButtons.length) {
                        resetButtons.forEach(function(button) {
                                button.addEventListener('click', function() {
                                        document.querySelectorAll('.hw-filter-drawer__body input, .hw-filter-drawer__body select').forEach(function(input) {
                                                if (input.type === 'checkbox') {
                                                        input.checked = false;
                                                } else if (input.type === 'range') {
                                                        const defaultValue = input.dataset.default || (input.id === 'hw-price-min' ? input.min : input.max);
                                                        input.value = defaultValue || '';
                                                } else {
                                                        input.value = '';
                                                }
                                        });

                                        if (sortSelect) {
                                                sortSelect.value = 'discount-desc';
                                        }

                                        updatePriceSliderLabels();

                                        if (filterDrawer) {
                                                filterDrawer.classList.remove('is-open');
                                        }

                                        const hash = window.location.hash || '';
                                        window.location.href = window.location.pathname + hash;
                                });
                        });
                }
        }

        function initializePriceSlider() {
                const minPrice = document.querySelector('#hw-price-min');
                const maxPrice = document.querySelector('#hw-price-max');
                const minValueInput = document.querySelector('[data-min-value]');
                const maxValueInput = document.querySelector('[data-max-value]');

                if (!minPrice || !maxPrice) return;

                // Calculate dynamic min/max from products
                calculateDynamicPriceRange();

                const handleInput = function(event) {
                        if (event?.target === minPrice) {
                                minPrice.dataset.touched = '1';
                        } else if (event?.target === maxPrice) {
                                maxPrice.dataset.touched = '1';
                        }

                        activePriceInput = null;
                        updatePriceSliderLabels();
                };

                const parseManualValue = function(value) {
                        if (typeof value !== 'string') {
                                value = value != null ? String(value) : '';
                        }

                        const numeric = value.replace(/[^0-9]/g, '');
                        if (!numeric) return NaN;
                        return parseInt(numeric, 10);
                };

                const handleManualInput = function(event) {
                        const target = event.target;
                        if (!target) return;

                        const parsedValue = parseManualValue(target.value);
                        const slider = target === minValueInput ? minPrice : maxPrice;
                        if (!slider) return;

                        const sliderMin = parseInt(slider.min || '0', 10);
                        const sliderMax = parseInt(slider.max || '0', 10);
                        let nextValue = Number.isNaN(parsedValue) ? null : parsedValue;

                        if (nextValue === null) {
                                if (target === minValueInput) {
                                        slider.value = slider.min || '0';
                                        delete slider.dataset.touched;
                                } else {
                                        slider.value = slider.max || slider.min || '0';
                                        delete slider.dataset.touched;
                                }
                        } else {
                                nextValue = Math.max(sliderMin, Math.min(sliderMax, nextValue));
                                slider.value = String(nextValue);
                                slider.dataset.touched = '1';

                                if (slider === minPrice) {
                                        const currentMax = parseInt(maxPrice.value || maxPrice.max || '0', 10);
                                        if (nextValue > currentMax) {
                                                maxPrice.value = String(nextValue);
                                                maxPrice.dataset.touched = '1';
                                        }
                                } else {
                                        const currentMin = parseInt(minPrice.value || minPrice.min || '0', 10);
                                        if (nextValue < currentMin) {
                                                minPrice.value = String(nextValue);
                                                minPrice.dataset.touched = '1';
                                        }
                                }
                        }

                        activePriceInput = target;
                        updatePriceSliderLabels();
                };

                const handleManualBlur = function(event) {
                        const target = event.target;
                        if (!target) return;

                        const slider = target === minValueInput ? minPrice : maxPrice;
                        if (!slider) return;

                        if (target.value === '') {
                                if (slider === minPrice) {
                                        slider.value = slider.dataset.default || slider.min || '0';
                                } else {
                                        slider.value = slider.dataset.default || slider.max || slider.min || '0';
                                }
                                delete slider.dataset.touched;
                        }

                        activePriceInput = null;
                        updatePriceSliderLabels();
                };

                minPrice.addEventListener('input', handleInput);
                maxPrice.addEventListener('input', handleInput);
                minPrice.addEventListener('change', handleInput);
                maxPrice.addEventListener('change', handleInput);

                [minValueInput, maxValueInput].forEach(function(input) {
                        if (!input) return;
                        input.addEventListener('input', handleManualInput);
                        input.addEventListener('change', handleManualBlur);
                        input.addEventListener('blur', handleManualBlur);
                });

                updatePriceSliderLabels();
        }

        function calculateDynamicPriceRange() {
                const cards = document.querySelectorAll('.hw-onsale-card');
                if (cards.length === 0) return;

                let minFound = Infinity;
                let maxFound = 0;

                cards.forEach(function(card) {
                        const priceElement = card.querySelector('.hw-onsale-card__price');
                        if (!priceElement) return;

                        // Extract all numeric values from price HTML (regular and sale prices)
                        const priceHTML = priceElement.innerHTML;
                        const matches = priceHTML.match(/\d[\d,.]*\d|\d/g);
                        
                        if (matches) {
                                matches.forEach(function(match) {
                                        // Remove dots/commas and parse
                                        const price = parseInt(match.replace(/[.,]/g, ''), 10);
                                        
                                        if (!isNaN(price) && price > 0) {
                                                minFound = Math.min(minFound, price);
                                                maxFound = Math.max(maxFound, price);
                                        }
                                });
                        }
                });

                if (minFound !== Infinity && maxFound !== 0) {
                        const minSlider = document.querySelector('#hw-price-min');
                        const maxSlider = document.querySelector('#hw-price-max');

                        if (minSlider && maxSlider) {
                                const rangeMin = 0;
                                const step = parseInt(minSlider.step || '1000', 10) || 1000;
                                const roundedMin = Math.max(rangeMin, Math.floor(minFound / step) * step);
                                const roundedMax = Math.ceil(maxFound / step) * step;

                                minSlider.min = rangeMin;
                                minSlider.max = roundedMax;
                                minSlider.dataset.default = String(roundedMin);
                                minSlider.dataset.displayDefault = String(minFound);

                                const currentMinValue = parseInt(minSlider.value || '', 10);
                                if (!minSlider.value || Number.isNaN(currentMinValue) || currentMinValue === rangeMin) {
                                        minSlider.value = String(roundedMin);
                                }

                                maxSlider.min = rangeMin;
                                maxSlider.max = roundedMax;
                                maxSlider.dataset.default = String(roundedMax);
                                maxSlider.dataset.displayDefault = String(maxFound);

                                const currentMaxValue = parseInt(maxSlider.value || '', 10);
                                if (
                                        !maxSlider.value ||
                                        Number.isNaN(currentMaxValue) ||
                                        currentMaxValue === 1000000 ||
                                        currentMaxValue > roundedMax ||
                                        currentMaxValue < roundedMin
                                ) {
                                        maxSlider.value = String(roundedMax);
                                }

                                delete minSlider.dataset.touched;
                                delete maxSlider.dataset.touched;

                                updatePriceSliderLabels();
                        }
                }
        }

        function updatePriceSliderLabels() {
                const minPrice = document.querySelector('#hw-price-min');
                const maxPrice = document.querySelector('#hw-price-max');
                const minLabel = document.querySelector('[data-min-value]');
                const maxLabel = document.querySelector('[data-max-value]');
                const slider = minPrice ? minPrice.closest('.hw-price-slider') : null;

                if (!minPrice || !maxPrice || !minLabel || !maxLabel) return;

                const defaultMinValue = parseInt(minPrice.dataset.default || minPrice.min || '0', 10);
                const defaultMaxValue = parseInt(maxPrice.dataset.default || maxPrice.max || '0', 10);
                const displayDefaultMin = parseInt(minPrice.dataset.displayDefault || defaultMinValue || '0', 10);
                const displayDefaultMax = parseInt(maxPrice.dataset.displayDefault || defaultMaxValue || '0', 10);

                let minValue = parseInt(minPrice.value || defaultMinValue || minPrice.min || '0', 10);
                let maxValue = parseInt(maxPrice.value || defaultMaxValue || maxPrice.max || '0', 10);

                if (Number.isNaN(minValue)) {
                        minValue = defaultMinValue;
                }

                if (Number.isNaN(maxValue)) {
                        maxValue = defaultMaxValue;
                }

                if (minValue > maxValue) {
                        if (document.activeElement === minPrice) {
                                maxValue = minValue;
                                maxPrice.value = maxValue;
                        } else {
                                minValue = maxValue;
                                minPrice.value = minValue;
                        }
                }

                const minTouched = minPrice.dataset.touched === '1';
                const maxTouched = maxPrice.dataset.touched === '1';

                const shouldUseDefaultMin = !minTouched && parseInt(minPrice.value || '', 10) === defaultMinValue;
                const shouldUseDefaultMax = !maxTouched && parseInt(maxPrice.value || '', 10) === defaultMaxValue;

                const displayMin = shouldUseDefaultMin ? displayDefaultMin : minValue;
                const displayMax = shouldUseDefaultMax ? displayDefaultMax : maxValue;

                const formattedMin = rupiahFormatter.format(displayMin).replace(/\u00a0/g, ' ');
                const formattedMax = rupiahFormatter.format(displayMax).replace(/\u00a0/g, ' ');

                if (activePriceInput !== minLabel) {
                        if ('value' in minLabel) {
                                minLabel.value = formattedMin;
                        } else {
                                minLabel.textContent = formattedMin;
                        }
                }

                if (activePriceInput !== maxLabel) {
                        if ('value' in maxLabel) {
                                maxLabel.value = formattedMax;
                        } else {
                                maxLabel.textContent = formattedMax;
                        }
                }

                if (slider) {
                        const sliderMin = parseInt(minPrice.min || '0', 10);
                        const sliderMax = parseInt(maxPrice.max || '0', 10);
                        const range = Math.max(sliderMax - sliderMin, 1);
                        const minPercent = ((minValue - sliderMin) / range) * 100;
                        const maxPercent = ((maxValue - sliderMin) / range) * 100;

                        const clamp = function(value) {
                                if (Number.isNaN(value)) return 0;
                                return Math.min(100, Math.max(0, value));
                        };

                        slider.style.setProperty('--range-start', clamp(minPercent) + '%');
                        slider.style.setProperty('--range-end', clamp(maxPercent) + '%');
                }
        }

        function applyTitleClamp() {
                const titles = document.querySelectorAll('.hw-onsale-card__title');

                titles.forEach(function(title) {
                        title.classList.remove('is-clamped');

                        const computed = window.getComputedStyle(title);
                        const lineHeight = parseFloat(computed.lineHeight);

                        if (!lineHeight) return;

                        const maxHeight = lineHeight * 3;

                        if (title.scrollHeight - 1 > maxHeight) {
                                title.classList.add('is-clamped');
                        }
                });
        }

        function scheduleTitleClamp() {
                if (titleClampTimer) {
                        clearTimeout(titleClampTimer);
                }

                titleClampTimer = setTimeout(function() {
                        applyTitleClamp();
                }, 150);
        }

	/**
	 * Update URL with filter/sort params and reload
	 */
        function updateURLAndReload() {
                const params = new URLSearchParams();

		// Sort
		const sortSelect = document.querySelector('.hw-sort-select');
		if (sortSelect && sortSelect.value && sortSelect.value !== 'discount-desc') {
			params.set('orderby', sortSelect.value);
		}

		// Price range
                const minPrice = document.querySelector('#hw-price-min');
                const maxPrice = document.querySelector('#hw-price-max');
                if (minPrice && minPrice.value) {
                        const defaultMin = minPrice.dataset.default || minPrice.min;
                        if (minPrice.dataset.touched === '1' || (defaultMin && minPrice.value !== defaultMin)) {
                                const minValue = parseInt(minPrice.value, 10);
                                if (!Number.isNaN(minValue)) params.set('min_price', minValue);
                        }
                }
                if (maxPrice && maxPrice.value) {
                        const defaultMax = maxPrice.dataset.default || maxPrice.max;
                        if (maxPrice.dataset.touched === '1' || (defaultMax && maxPrice.value !== defaultMax)) {
                                const maxValue = parseInt(maxPrice.value, 10);
                                if (!Number.isNaN(maxValue)) params.set('max_price', maxValue);
                        }
                }

                // Categories - collect checked child categories
                const categoryCheckboxes = document.querySelectorAll('input[name="categories[]"]:checked');
		if (categoryCheckboxes.length > 0) {
			const categoryIds = Array.from(categoryCheckboxes).map(cb => cb.value);
			params.set('categories', categoryIds.join(','));
		}

		// Update URL and reload
		const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
		window.location.href = newURL;
	}

	/**
	 * Show toast notification
	 */
	function showToast(message, type) {
		type = type || 'info';
		const container = document.querySelector('.hw-toast-container');
		if (!container) return;

		const toast = document.createElement('div');
		toast.className = 'hw-toast hw-toast--' + type;
		
		const icon = document.createElement('span');
		icon.className = 'hw-toast__icon';
		icon.innerHTML = type === 'success' ? '✓' : (type === 'error' ? '✕' : 'ⓘ');
		
		const messageEl = document.createElement('span');
		messageEl.className = 'hw-toast__message';
		messageEl.textContent = message;
		
		const closeBtn = document.createElement('button');
		closeBtn.className = 'hw-toast__close';
		closeBtn.innerHTML = '×';
		closeBtn.setAttribute('aria-label', 'Close notification');
		closeBtn.addEventListener('click', function() {
			removeToast(toast);
		});
		
		toast.appendChild(icon);
		toast.appendChild(messageEl);
		toast.appendChild(closeBtn);
		
		container.appendChild(toast);
		
		// Auto-remove after 4 seconds
		setTimeout(function() {
			removeToast(toast);
		}, 4000);
	}

	/**
	 * Remove toast notification
	 */
	function removeToast(toast) {
		if (!toast || !toast.parentNode) return;
		
		toast.classList.add('is-removing');
		
		setTimeout(function() {
			if (toast.parentNode) {
				toast.parentNode.removeChild(toast);
			}
		}, 300);
	}

})();
