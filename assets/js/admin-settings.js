(function ($) {
  'use strict';

  function updateRowNames($container) {
    $container.find('.ais-social-row').each(function (index) {
      const $row = $(this);
      $row.attr('data-index', index);
      $row.find('[data-name]').each(function () {
        const $field = $(this);
        const template = $field.data('name');
        if (!template) return;
        $field.attr('name', template.replace(/__index__/g, index));
      });
    });
  }

  function toggleOtherLabel($row) {
    const isOther = $row.find('.ais-social-network-select').val() === 'other';
    $row.find('.ais-social-other-label').toggleClass('hidden', !isOther);
  }

  function createSocialRowTemplate() {
    const template = document.getElementById('tmpl-ais-social-row');
    if (!template) return null;
    const html = template.innerHTML.trim();
    return $(html);
  }

  function initSocialRows() {
    const $container = $('#ais-social-rows');
    if (!$container.length) {
      return;
    }

    $container.on('click', '.ais-remove-social', function (event) {
      event.preventDefault();
      const $rows = $container.find('.ais-social-row');
      if ($rows.length <= 1) {
        $rows.find('input[type="text"]').val('');
        $rows.find('select').val('facebook').trigger('change');
        return;
      }
      $(this).closest('.ais-social-row').remove();
      updateRowNames($container);
    });

    $container.on('change', '.ais-social-network-select', function () {
      toggleOtherLabel($(this).closest('.ais-social-row'));
    });

    $('#ais-add-social').on('click', function (event) {
      event.preventDefault();
      const $row = createSocialRowTemplate();
      if (!$row) return;
      $container.append($row);
      updateRowNames($container);
      toggleOtherLabel($row);
    });

    $container.find('.ais-social-row').each(function () {
      toggleOtherLabel($(this));
    });

    updateRowNames($container);
  }

  function initLanguageTags() {
    const map = (window.aisSettings && aisSettings.languageOptions) || {};
    const removeTemplate = (window.aisSettings && aisSettings.i18nLanguageRemove) || '%s';
    const $container = $('#ais-language-tags');
    if (!$container.length) {
      return;
    }

    const nameTemplate = $container.data('name');
    const $select = $('#ais-language-select');

    function languageSelected(code) {
      return $container.find('.ais-language-tag[data-code="' + code + '"]').length > 0;
    }

    function addTag(code) {
      if (!map[code] || languageSelected(code)) return;
      const label = map[code];
      const removeLabel = removeTemplate.replace('%s', label);

      const $tag = $(
        '<span class="ais-language-tag" data-code="' + code + '">' +
          '<span class="ais-language-label"></span>' +
          '<button type="button" class="button-link-delete ais-language-remove" aria-label="' + removeLabel + '">' +
            '<span aria-hidden="true">&times;</span>' +
            '<span class="screen-reader-text">' + removeLabel + '</span>' +
          '</button>' +
        '</span>'
      );

      $tag.find('.ais-language-label').text(label);
      const $hidden = $('<input>', {
        type: 'hidden',
        name: nameTemplate,
        value: code,
      });
      $tag.append($hidden);
      $container.append($tag);
    }

    function refreshSelect() {
      if (!$select.length) return;
      $select.find('option').each(function () {
        const value = $(this).val();
        if (!value) return;
        const selected = languageSelected(value);
        $(this).prop('disabled', selected);
      });
    }

    $container.on('click', '.ais-language-remove', function (event) {
      event.preventDefault();
      const $tag = $(this).closest('.ais-language-tag');
      $tag.remove();
      refreshSelect();
    });

    if ($select.length) {
      $select.on('change', function () {
        const code = $(this).val();
        if (!code) return;
        addTag(code);
        $(this).val('');
        refreshSelect();
      });
    }

    // Ensure pre-rendered tags are registered.
    $container.find('.ais-language-tag').each(function () {
      const code = $(this).data('code');
      if (!map[code]) {
        $(this).remove();
      }
    });

    refreshSelect();
  }

  function createOpeningHoursRowTemplate() {
    const template = document.getElementById('tmpl-ais-opening-hours-row');
    if (!template) return null;
    const html = template.innerHTML.trim();
    return $(html);
  }

  function updateOpeningHoursNames($container) {
    $container.find('.ais-opening-hours-row').each(function (index) {
      const $row = $(this);
      $row.attr('data-index', index);
      $row.find('[data-name]').each(function () {
        const $field = $(this);
        const template = $field.data('name');
        if (!template) return;
        $field.attr('name', template.replace(/__index__/g, index));
      });
    });
  }

  function normalizeDay(day) {
    return (day || '').trim();
  }

  function getDayToggles() {
    const toggles = {};
    $('.ais-opening-hours-toggles input[type="checkbox"]').each(function () {
      const dayKey = $(this).data('day-key');
      if (dayKey) {
        toggles[dayKey] = $(this).is(':checked');
      }
    });
    return toggles;
  }

  function dayEnabled(day) {
    const toggles = getDayToggles();
    if (toggles.hasOwnProperty(day)) {
      return toggles[day];
    }
    return true;
  }

  function applyDayToggleToSelects() {
    const toggles = getDayToggles();
    $('.ais-opening-hours-day').each(function () {
      const $select = $(this);
      const value = $select.val();
      $select.find('option').each(function () {
        const $option = $(this);
        const day = $option.val();
        const enabled = toggles.hasOwnProperty(day) ? toggles[day] : true;
        $option.prop('disabled', !enabled);
      });
      if (toggles.hasOwnProperty(value) && !toggles[value]) {
        $select.closest('.ais-opening-hours-row').remove();
      }
    });
    updateOpeningHoursNames($('#ais-opening-hours-rows'));
  }

  function addOpeningRow(dayKey, opens, closes) {
    if (!dayEnabled(dayKey)) {
      return;
    }
    const $container = $('#ais-opening-hours-rows');
    const $row = createOpeningHoursRowTemplate();
    if (!$row) return;
    $row.find('.ais-opening-hours-day').val(dayKey || 'Monday');
    $row.find('.ais-opening-hours-opens').val(opens || '');
    $row.find('.ais-opening-hours-closes').val(closes || '');
    $row.attr('draggable', true);
    $container.append($row);
    applyDayToggleToSelects();
    updateOpeningHoursNames($container);
  }

  function resetOpeningRows(rows) {
    const $container = $('#ais-opening-hours-rows');
    if (!$container.length) return;
    $container.empty();
    const toggles = getDayToggles();
    (rows || []).forEach(function (row) {
      const day = row.day_key || row.day || 'Monday';
      if (toggles.hasOwnProperty(day) && !toggles[day]) {
        return;
      }
      if (Array.isArray(row.slots) && row.slots.length) {
        row.slots.forEach(function (slot) {
          addOpeningRow(day, slot.opens || '', slot.closes || '');
        });
      } else {
        addOpeningRow(day, row.opens || '', row.closes || '');
      }
    });
    updateOpeningHoursNames($container);
  }

  function getCurrentOpeningRows() {
    const rows = [];
    $('#ais-opening-hours-rows .ais-opening-hours-row').each(function () {
      const $row = $(this);
      rows.push({
        day_key: normalizeDay($row.find('.ais-opening-hours-day').val()),
        opens: ($row.find('.ais-opening-hours-opens').val() || '').trim(),
        closes: ($row.find('.ais-opening-hours-closes').val() || '').trim(),
      });
    });
    return rows;
  }

  function addTemplateButtons() {
    const templates = {
      weekday: [
        { day_key: 'Monday', slots: [{ opens: '10:00', closes: '19:00' }] },
        { day_key: 'Tuesday', slots: [{ opens: '10:00', closes: '19:00' }] },
        { day_key: 'Wednesday', slots: [{ opens: '10:00', closes: '19:00' }] },
        { day_key: 'Thursday', slots: [{ opens: '10:00', closes: '19:00' }] },
        { day_key: 'Friday', slots: [{ opens: '10:00', closes: '19:00' }] },
      ],
      weekend: [
        { day_key: 'Saturday', slots: [{ opens: '10:00', closes: '17:00' }] },
        { day_key: 'Sunday', slots: [{ opens: '10:00', closes: '17:00' }] },
        { day_key: 'PublicHoliday', slots: [{ opens: '10:00', closes: '17:00' }] },
      ],
      allDay: [
        { day_key: 'Monday', slots: [{ opens: '00:00', closes: '23:59' }] },
        { day_key: 'Tuesday', slots: [{ opens: '00:00', closes: '23:59' }] },
        { day_key: 'Wednesday', slots: [{ opens: '00:00', closes: '23:59' }] },
        { day_key: 'Thursday', slots: [{ opens: '00:00', closes: '23:59' }] },
        { day_key: 'Friday', slots: [{ opens: '00:00', closes: '23:59' }] },
        { day_key: 'Saturday', slots: [{ opens: '00:00', closes: '23:59' }] },
        { day_key: 'Sunday', slots: [{ opens: '00:00', closes: '23:59' }] },
        { day_key: 'PublicHoliday', slots: [{ opens: '00:00', closes: '23:59' }] },
      ],
    };

    $('#ais-oh-template-weekday').on('click', function (e) {
      e.preventDefault();
      resetOpeningRows(templates.weekday);
    });

    $('#ais-oh-template-weekend').on('click', function (e) {
      e.preventDefault();
      $('#ais-include-saturday, #ais-include-sunday, #ais-include-holiday').prop('checked', true);
      resetOpeningRows(templates.weekend);
    });

    $('#ais-oh-template-24h').on('click', function (e) {
      e.preventDefault();
      $('.ais-opening-hours-toggles input[type="checkbox"]').prop('checked', true);
      resetOpeningRows(templates.allDay);
    });

    $('#ais-oh-template-clear').on('click', function (e) {
      e.preventDefault();
      resetOpeningRows([]);
    });
  }

  function ensureDayHasSlot(dayKey) {
    const rows = getCurrentOpeningRows();
    const exists = rows.some(function (row) {
      return row.day_key === dayKey;
    });
    if (!exists) {
      addOpeningRow(dayKey, '10:00', '17:00');
    }
  }

  function initDayToggles() {
    const $container = $('#ais-opening-hours-rows');
    if (!$container.length) return;
    $('.ais-opening-hours-toggles input[type="checkbox"]').on('change', function () {
      const dayKey = $(this).data('day-key');
      if (!dayKey) {
        return;
      }
      if (dayKey === 'PublicHoliday') {
        $('#ais-holiday-enabled').prop('checked', $(this).is(':checked'));
      } else if (this.id === 'ais-holiday-enabled') {
        $('#ais-include-holiday').prop('checked', $(this).is(':checked'));
      }
      if ($(this).is(':checked')) {
        ensureDayHasSlot(dayKey);
      } else {
        $container.find('.ais-opening-hours-day').each(function () {
          const $select = $(this);
          if ($select.val() === dayKey) {
            $select.closest('.ais-opening-hours-row').remove();
          }
        });
      }
      applyDayToggleToSelects();
      updateOpeningHoursNames($container);
    });
    applyDayToggleToSelects();
  }
  function applyHolidayMode() {
    const enabled = $('#ais-holiday-enabled').is(':checked');
    const mode = $('input[name="ai_search_schema_options[holiday_mode]"]:checked').val() || 'custom';
    if (!enabled) {
      $('#ais-include-holiday').prop('checked', false).trigger('change');
      return;
    }
    $('#ais-include-holiday').prop('checked', true);
    if (mode === 'weekday') {
      const rows = getCurrentOpeningRows().filter(function (row) {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'].includes(row.day_key);
      });
      const holidayRows = rows.map(function (row) {
        return { day_key: 'PublicHoliday', opens: row.opens, closes: row.closes, slots: [{ opens: row.opens, closes: row.closes }] };
      });
      // Remove existing holiday rows and inject weekday copies.
      $('#ais-opening-hours-rows .ais-opening-hours-day').each(function () {
        if ($(this).val() === 'PublicHoliday') {
          $(this).closest('.ais-opening-hours-row').remove();
        }
      });
      resetOpeningRows(holidayRows.concat(getCurrentOpeningRows().filter(function (row) { return row.day_key !== 'PublicHoliday'; })));
    } else if (mode === 'weekend') {
      $('#ais-opening-hours-rows .ais-opening-hours-day').each(function () {
        if ($(this).val() === 'PublicHoliday') {
          $(this).closest('.ais-opening-hours-row').remove();
        }
      });
      addOpeningRow('PublicHoliday', '10:00', '17:00');
    }
  }

  function initOpeningHours() {
    const $container = $('#ais-opening-hours-rows');
    if (!$container.length) {
      return;
    }

    $('#ais-add-opening-hour').on('click', function (event) {
      event.preventDefault();
      addOpeningRow('Monday', '', '');
    });

    $container.on('click', '.ais-remove-opening-hour', function (event) {
      event.preventDefault();
      $(this).closest('.ais-opening-hours-row').remove();
      updateOpeningHoursNames($container);
    });

    addTemplateButtons();
    initDayToggles();
    $('#ais-holiday-enabled').on('change', applyHolidayMode);
    $('input[name="ai_search_schema_options[holiday_mode]"]').on('change', applyHolidayMode);
    initOpeningHoursDrag();
    updateOpeningHoursNames($container);
    applyHolidayMode();
  }

  function initOpeningHoursDrag() {
    const $container = $('#ais-opening-hours-rows');
    if (!$container.length) return;
    let dragSrc = null;

    $container.on('dragstart', '.ais-opening-hours-row', function (e) {
      dragSrc = this;
      $(this).addClass('is-dragging');
      e.originalEvent.dataTransfer.effectAllowed = 'move';
      e.originalEvent.dataTransfer.setData('text/plain', 'drag');
    });

    $container.on('dragend', '.ais-opening-hours-row', function () {
      dragSrc = null;
      $container.find('.ais-opening-hours-row').removeClass('is-dragging drag-over');
      updateOpeningHoursNames($container);
    });

    $container.on('dragover', '.ais-opening-hours-row', function (e) {
      if (!dragSrc || dragSrc === this) return;
      e.preventDefault();
      e.originalEvent.dataTransfer.dropEffect = 'move';
      $(this).addClass('drag-over');
    });

    $container.on('dragleave', '.ais-opening-hours-row', function () {
      $(this).removeClass('drag-over');
    });

    $container.on('drop', '.ais-opening-hours-row', function (e) {
      if (!dragSrc || dragSrc === this) return;
      e.preventDefault();
      $(this).removeClass('drag-over');
      const $target = $(this);
      const after = e.originalEvent.offsetY > $target.outerHeight() / 2;
      if (after) {
        $target.after(dragSrc);
      } else {
        $target.before(dragSrc);
      }
      updateOpeningHoursNames($container);
    });
  }

  function initGeocodeLookup() {
    const $button = $('#ais-geocode-button');
    if (!$button.length || !window.aisSettings) {
      return;
    }

    const settings = window.aisSettings;
    const ajaxUrl = settings.ajaxUrl || window.ajaxurl;
    const hasApiKey = !!settings.hasGeocodeApiKey;
    const $status = $('#ais-geocode-status');
    const defaultLabel = $button.text().trim() || settings.i18nGeocodeReady || $button.text();

    if (!$button.text().trim() && settings.i18nGeocodeReady) {
      $button.text(settings.i18nGeocodeReady);
    }

    function setStatus(message, isError) {
      if (!$status.length) {
        return;
      }
      $status.text(message || '');
      $status.toggleClass('is-error', !!isError);
    }

    $button.on('click', function (event) {
      event.preventDefault();

      if (!ajaxUrl) {
        setStatus('AJAX endpoint is unavailable.', true);
        return;
      }

      function applyComponents(components) {
        if (!components || typeof components !== 'object') {
          return;
        }

        if (components.address_locality) {
          $('#ais-address-locality').val(components.address_locality);
        }
        if (components.street_address) {
          $('#ais-address-street').val(components.street_address);
        }
        if (components.postal_code) {
          $('#ais-address-postal').val(components.postal_code);
        }
        if (components.country) {
          $('#ais-address-country').val(components.country);
        }

        if (components.address_region) {
          const $prefecture = $('#ais-address-prefecture');
          const $hiddenRegion = $('#ais-address-region-hidden');
          const optionExists = $prefecture.find('option[value="' + components.address_region + '"]').length > 0;

          if (optionExists) {
            $prefecture.val(components.address_region).trigger('change');
          } else {
            if ($prefecture.length) {
              $prefecture.val('');
            }
            if ($hiddenRegion.length) {
              $hiddenRegion.val(components.address_region);
            }
            $('#ais-prefecture-iso').text('—');
          }
        }
      }

      const $prefecture = $('#ais-address-prefecture');
      const $regionHidden = $('#ais-address-region-hidden');
      const prefectureValue = $prefecture.length ? ($prefecture.val() || '') : '';
      if ($regionHidden.length) {
        $regionHidden.val(prefectureValue);
      }

      const address = {
        postal_code: $('#ais-address-postal').val(),
        region: $regionHidden.length ? $regionHidden.val() : prefectureValue,
        locality: $('#ais-address-locality').val(),
        street_address: $('#ais-address-street').val(),
        country: $('#ais-address-country').val(),
      };

      let hasAddress = false;
      $.each(address, function (_, value) {
        if ($.trim(value || '') !== '') {
          hasAddress = true;
          return false;
        }
      });

      if (!hasAddress) {
        setStatus(settings.i18nGeocodeMissingAddress || '', true);
        return;
      }

      if (!hasApiKey && settings.i18nGeocodeMissingKey) {
        setStatus(settings.i18nGeocodeMissingKey, false);
      } else {
        setStatus('');
      }
      $button.prop('disabled', true).text(settings.i18nGeocodeWorking || defaultLabel);

      $.ajax({
        url: ajaxUrl,
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'ai_search_schema_geocode',
          nonce: settings.geocodeNonce || '',
          address: address,
        },
      })
        .done(function (response) {
          if (response && response.success && response.data) {
            if (response.data.latitude) {
              $('#ais-geo-latitude').val(response.data.latitude);
            }
            if (response.data.longitude) {
              $('#ais-geo-longitude').val(response.data.longitude);
            }
            if (response.data.components) {
              applyComponents(response.data.components);
            }
            if (response.data.notice) {
              setStatus(response.data.notice, false);
            } else {
              setStatus(settings.i18nGeocodeSuccess || '');
            }
          } else if (response && response.data && response.data.message) {
            setStatus(response.data.message, true);
          } else {
            setStatus(settings.i18nGeocodeFailure || '', true);
          }
        })
        .fail(function () {
          setStatus(settings.i18nGeocodeFailure || '', true);
        })
        .always(function () {
          $button.prop('disabled', false).text(defaultLabel);
        });
    });
  }

  function initGeocodeKeyControls() {
    const $clearButton = $('#ais-gmaps-api-key-clear');
    if ($clearButton.length) {
      $clearButton.on('click', function (event) {
        const message = $(this).data('confirm');
        if (message && !window.confirm(message)) {
          event.preventDefault();
        }
      });
    }
  }

  function initImageSelector(config) {
    const $selectButton = $(config.selectButton);
    if (!$selectButton.length) {
      return;
    }

    const $removeButton = $(config.removeButton);
    const $urlInput = $(config.urlInput);
    const $idInput = $(config.idInput);
    const $preview = $(config.preview);
    let frame;

    function toggleRemoveButton(hasUrl) {
      if ($removeButton && $removeButton.length) {
        $removeButton.toggleClass('hidden', !hasUrl);
      }
    }

    function updatePreview(url) {
      if ($preview && $preview.length) {
        if (url) {
          $preview.html('<img src="' + url + '" alt="" style="max-width:150px;height:auto;" />');
        } else {
          $preview.empty();
        }
      }
      toggleRemoveButton(!!url);
    }

    $selectButton.on('click', function (event) {
      event.preventDefault();

      if (frame) {
        frame.open();
        return;
      }

      frame = wp.media({
        title: $selectButton.data('title'),
        button: {
          text: $selectButton.data('button'),
        },
        library: {
          type: 'image',
        },
        multiple: false,
      });

      frame.on('select', function () {
        const attachment = frame.state().get('selection').first().toJSON();
        if ($urlInput && $urlInput.length) {
          $urlInput.val(attachment.url);
        }
        if ($idInput && $idInput.length) {
          $idInput.val(attachment.id);
        }
        updatePreview(attachment.url);
      });

      frame.open();
    });

    if ($removeButton && $removeButton.length) {
      $removeButton.on('click', function (event) {
        event.preventDefault();
        if ($urlInput && $urlInput.length) {
          $urlInput.val('');
        }
        if ($idInput && $idInput.length) {
          $idInput.val('');
        }
        updatePreview('');
      });
    }

    updatePreview($urlInput && $urlInput.length ? $urlInput.val() : '');
  }

  function relocateNotices() {
    const $hero = $('.ais-settings-hero');
    if (!$hero.length) {
      return;
    }
    $hero.find('.notice').each(function () {
      const $notice = $(this);
      $notice.addClass('ais-elevated-notice');
      $notice.insertBefore($hero);
    });
  }

  function initPrefectureSelector() {
    const $prefecture = $('#ais-address-prefecture');
    if (!$prefecture.length) {
      return;
    }
    const $regionHidden = $('#ais-address-region-hidden');
    const $isoOutput = $('#ais-prefecture-iso');

    function syncPrefectureMeta() {
      const $selected = $prefecture.find('option:selected');
      const iso = $selected.data('iso') || '';
      if ($regionHidden.length) {
        $regionHidden.val($prefecture.val() || '');
      }
      if ($isoOutput.length) {
        $isoOutput.text(iso || '—');
      }
    }

    $prefecture.on('change', syncPrefectureMeta);
    syncPrefectureMeta();
  }

  function initZipAutofill() {
    const $postal = $('#ais-address-postal');
    if (!$postal.length) {
      return;
    }

    function fillAddress(result) {
      if (!result) return;
      const prefecture = result.address1 || '';
      const city = result.address2 || '';
      const line = result.address3 || '';

      if (prefecture) {
        const $pref = $('#ais-address-prefecture');
        $pref.val(prefecture).trigger('change');
      }
      if (city) {
        $('#ais-address-city').val(city);
      }
      if (line) {
        $('#ais-address-line').val(line);
      }
      // Update LocalBusiness guide after autofill
      updateLocalBusinessGuide();
    }

    function lookup(zip) {
      if (!zip || zip.length !== 7) return;
      const url = 'https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + encodeURIComponent(zip);
      fetch(url)
        .then((res) => res.json())
        .then((data) => {
          if (data && data.results && data.results.length) {
            fillAddress(data.results[0]);
          }
        })
        .catch(() => {});
    }

    $postal.on('keyup', function () {
      const digits = ($(this).val() || '').replace(/[^0-9]/g, '');
      if (digits.length === 7) {
        lookup(digits);
      }
    });

    $('#ais-zip-autofill').on('click', function (e) {
      e.preventDefault();
      const digits = ($postal.val() || '').replace(/[^0-9]/g, '');
      lookup(digits);
    });
  }

  /**
   * LocalBusiness Google 推奨項目ガイドのリアルタイムチェック
   */
  function updateLocalBusinessGuide() {
    const $guide = $('#ais-lb-guide');
    if (!$guide.length) {
      return;
    }

    // チェック条件の定義
    const checks = {
      name: function() {
        return $.trim($('#ais-company-name').val() || '') !== '';
      },
      address: function() {
        const prefecture = $.trim($('#ais-address-prefecture').val() || '');
        const city = $.trim($('#ais-address-city').val() || '');
        return prefecture !== '' && city !== '';
      },
      phone: function() {
        return $.trim($('#ais-phone').val() || '') !== '';
      },
      geo: function() {
        const lat = $.trim($('#ais-geo-latitude').val() || '');
        const lng = $.trim($('#ais-geo-longitude').val() || '');
        return lat !== '' && lng !== '';
      },
      hours: function() {
        return $('#ais-opening-hours-rows .ais-opening-hours-row').length > 0;
      },
      image: function() {
        const storeImage = $.trim($('#ais-store-image-url').val() || '');
        const lbImage = $.trim($('#ais-lb-image-url').val() || '');
        return storeImage !== '' || lbImage !== '';
      },
      url: function() {
        return $.trim($('#ais-site-url').val() || '') !== '';
      },
      price: function() {
        return $.trim($('#ais-price-range').val() || '') !== '';
      }
    };

    // 各チェック項目を更新
    $.each(checks, function(key, checkFn) {
      const $item = $('#ais-lb-check-' + key).closest('.ais-lb-guide__item');
      const $check = $('#ais-lb-check-' + key);
      const isComplete = checkFn();

      if (isComplete) {
        $item.addClass('is-complete');
        $check.text('✓');
      } else {
        $item.removeClass('is-complete');
        $check.text('○');
      }
    });
  }

  /**
   * Show dynamic help text based on entity type selection.
   * Guides users on how Organization and LocalBusiness can work together.
   */
  function initEntityTypeHelp() {
    const $entityType = $('#ais-entity-type');
    const $tooltipText = $('#ais-entity-type-tooltip .ais-field__tooltip-text');

    if (!$entityType.length || !$tooltipText.length) {
      return;
    }

    const helpTexts = {
      Organization: window.aisSettings?.i18nEntityHelpOrg || '',
      LocalBusiness: window.aisSettings?.i18nEntityHelpLB || ''
    };

    function updateHelpText() {
      const selected = $entityType.val();
      const helpText = helpTexts[selected] || '';
      $tooltipText.text(helpText);
    }

    // Apply on page load and change
    updateHelpText();
    $entityType.on('change', updateHelpText);
  }

  function initLocalBusinessGuide() {
    const $guide = $('#ais-lb-guide');
    if (!$guide.length) {
      return;
    }

    // 関連フィールドの変更を監視
    const watchFields = [
      '#ais-company-name',
      '#ais-address-prefecture',
      '#ais-address-city',
      '#ais-address-line',
      '#ais-phone',
      '#ais-geo-latitude',
      '#ais-geo-longitude',
      '#ais-store-image-url',
      '#ais-lb-image-url',
      '#ais-site-url',
      '#ais-price-range'
    ];

    $(watchFields.join(', ')).on('input change', function() {
      updateLocalBusinessGuide();
    });

    // 営業時間行の追加・削除を監視
    const $hoursContainer = $('#ais-opening-hours-rows');
    if ($hoursContainer.length) {
      const observer = new MutationObserver(function() {
        updateLocalBusinessGuide();
      });
      observer.observe($hoursContainer[0], { childList: true });
    }

    // 初回チェック
    updateLocalBusinessGuide();
  }

  $(document).ready(function () {
    initImageSelector({
      selectButton: '#ais-logo-select',
      removeButton: '#ais-logo-remove',
      urlInput: '#ais-logo-url',
      idInput: '#ais-logo-id',
      preview: '#ais-logo-preview',
    });
    initImageSelector({
      selectButton: '#ais-lb-image-select',
      removeButton: '#ais-lb-image-remove',
      urlInput: '#ais-lb-image-url',
      idInput: '#ais-lb-image-id',
      preview: '#ais-lb-image-preview',
    });
    initImageSelector({
      selectButton: '#ais-store-image-select',
      removeButton: '#ais-store-image-remove',
      urlInput: '#ais-store-image-url',
      idInput: '#ais-store-image-id',
      preview: '#ais-store-image-preview',
    });
    initSocialRows();
    initLanguageTags();
    initOpeningHours();
    initGeocodeKeyControls();
    initGeocodeLookup();
    initPrefectureSelector();
    initZipAutofill();
    initEntityTypeHelp();
    initLocalBusinessGuide();
    initValidationSummary();
    relocateNotices();
  });

  /**
   * バリデーションサマリーパネルの折りたたみ機能を初期化
   */
  function initValidationSummary() {
    const $summary = $('.ais-validation-summary');
    if (!$summary.length) {
      return;
    }

    const $toggle = $summary.find('.ais-validation-summary__toggle');
    const $details = $summary.find('.ais-validation-summary__details');

    // ヘッダークリックでもトグル
    $summary.find('.ais-validation-summary__header').on('click', function (e) {
      // ボタン自体のクリックは二重処理を避ける
      if ($(e.target).closest('.ais-validation-summary__toggle').length) {
        return;
      }
      toggleDetails();
    });

    $toggle.on('click', function (e) {
      e.stopPropagation();
      toggleDetails();
    });

    function toggleDetails() {
      const isExpanded = $toggle.attr('aria-expanded') === 'true';
      $toggle.attr('aria-expanded', !isExpanded);
      if (isExpanded) {
        $details.attr('hidden', '');
      } else {
        $details.removeAttr('hidden');
      }
    }
  }

  // llms.txt save button handler
  function initLlmsTxtSave() {
    const $button = $('#ais-save-llms-txt');
    const $spinner = $button.parent().find('.spinner');
    const $status = $button.parent().find('.ais-llms-txt-status');
    const $textarea = $('#ais-llms-txt-content');

    if (!$button.length || !$textarea.length) {
      return;
    }

    $button.on('click', function (e) {
      e.preventDefault();

      const originalText = $button.text();
      $button.prop('disabled', true).text(aisSettings.i18nLlmsTxtSaving || 'Saving...');
      $spinner.addClass('is-active');
      $status.text('');

      $.ajax({
        url: aisSettings.ajaxUrl,
        type: 'POST',
        data: {
          action: 'ai_search_schema_save_llms_txt',
          nonce: aisSettings.llmsTxtSaveNonce,
          content: $textarea.val()
        },
        success: function (response) {
          if (response.success) {
            $status.text(aisSettings.i18nLlmsTxtSaved || 'Saved!').css('color', '#46b450');
            setTimeout(function () {
              $status.fadeOut(function () {
                $status.text('').show().css('color', '');
              });
            }, 2000);
          } else {
            $status.text(response.data?.message || 'Failed to save').css('color', '#dc3232');
          }
        },
        error: function () {
          $status.text('Failed to save llms.txt').css('color', '#dc3232');
        },
        complete: function () {
          $button.prop('disabled', false).text(aisSettings.i18nLlmsTxtSave || originalText);
          $spinner.removeClass('is-active');
        }
      });
    });
  }

  // llms.txt regenerate button handler
  function initLlmsTxtRegenerate() {
    const $button = $('#ais-regenerate-llms-txt');
    const $spinner = $button.parent().find('.spinner');
    const $status = $button.parent().find('.ais-llms-txt-status');
    const $textarea = $('#ais-llms-txt-content');

    if (!$button.length || !$textarea.length) {
      return;
    }

    $button.on('click', function (e) {
      e.preventDefault();

      const originalText = $button.text();
      $button.prop('disabled', true).text(aisSettings.i18nLlmsTxtRegenerating || 'Regenerating...');
      $spinner.addClass('is-active');
      $status.text('');

      $.ajax({
        url: aisSettings.ajaxUrl,
        type: 'POST',
        data: {
          action: 'ai_search_schema_regenerate_llms_txt',
          nonce: aisSettings.llmsTxtNonce
        },
        success: function (response) {
          if (response.success && response.data && response.data.content) {
            $textarea.val(response.data.content);
            $status.text(response.data.message || 'Regenerated!').css('color', '#46b450');
            setTimeout(function () {
              $status.fadeOut(function () {
                $status.text('').show().css('color', '');
              });
            }, 2000);
          } else {
            $status.text(response.data?.message || 'Failed to regenerate').css('color', '#dc3232');
          }
        },
        error: function () {
          $status.text('Failed to regenerate llms.txt').css('color', '#dc3232');
        },
        complete: function () {
          $button.prop('disabled', false).text(aisSettings.i18nLlmsTxtRegenerate || originalText);
          $spinner.removeClass('is-active');
        }
      });
    });
  }

  $(document).ready(function () {
    initLlmsTxtSave();
    initLlmsTxtRegenerate();
  });
})(jQuery);
