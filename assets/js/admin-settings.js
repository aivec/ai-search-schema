(function ($) {
  'use strict';

  function updateRowNames($container) {
    $container.find('.avc-social-row').each(function (index) {
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
    const isOther = $row.find('.avc-social-network-select').val() === 'other';
    $row.find('.avc-social-other-label').toggleClass('hidden', !isOther);
  }

  function createSocialRowTemplate() {
    const template = document.getElementById('tmpl-avc-social-row');
    if (!template) return null;
    const html = template.innerHTML.trim();
    return $(html);
  }

  function initSocialRows() {
    const $container = $('#avc-social-rows');
    if (!$container.length) {
      return;
    }

    $container.on('click', '.avc-remove-social', function (event) {
      event.preventDefault();
      const $rows = $container.find('.avc-social-row');
      if ($rows.length <= 1) {
        $rows.find('input[type="text"]').val('');
        $rows.find('select').val('facebook').trigger('change');
        return;
      }
      $(this).closest('.avc-social-row').remove();
      updateRowNames($container);
    });

    $container.on('change', '.avc-social-network-select', function () {
      toggleOtherLabel($(this).closest('.avc-social-row'));
    });

    $('#avc-add-social').on('click', function (event) {
      event.preventDefault();
      const $row = createSocialRowTemplate();
      if (!$row) return;
      $container.append($row);
      updateRowNames($container);
      toggleOtherLabel($row);
    });

    $container.find('.avc-social-row').each(function () {
      toggleOtherLabel($(this));
    });

    updateRowNames($container);
  }

  function initLanguageTags() {
    const map = (window.aisSettings && aisSettings.languageOptions) || {};
    const removeTemplate = (window.aisSettings && aisSettings.i18nLanguageRemove) || '%s';
    const $container = $('#avc-language-tags');
    if (!$container.length) {
      return;
    }

    const nameTemplate = $container.data('name');
    const $select = $('#avc-language-select');

    function languageSelected(code) {
      return $container.find('.avc-language-tag[data-code="' + code + '"]').length > 0;
    }

    function addTag(code) {
      if (!map[code] || languageSelected(code)) return;
      const label = map[code];
      const removeLabel = removeTemplate.replace('%s', label);

      const $tag = $(
        '<span class="avc-language-tag" data-code="' + code + '">' +
          '<span class="avc-language-label"></span>' +
          '<button type="button" class="button-link-delete avc-language-remove" aria-label="' + removeLabel + '">' +
            '<span aria-hidden="true">&times;</span>' +
            '<span class="screen-reader-text">' + removeLabel + '</span>' +
          '</button>' +
        '</span>'
      );

      $tag.find('.avc-language-label').text(label);
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

    $container.on('click', '.avc-language-remove', function (event) {
      event.preventDefault();
      const $tag = $(this).closest('.avc-language-tag');
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
    $container.find('.avc-language-tag').each(function () {
      const code = $(this).data('code');
      if (!map[code]) {
        $(this).remove();
      }
    });

    refreshSelect();
  }

  function createOpeningHoursRowTemplate() {
    const template = document.getElementById('tmpl-avc-opening-hours-row');
    if (!template) return null;
    const html = template.innerHTML.trim();
    return $(html);
  }

  function updateOpeningHoursNames($container) {
    $container.find('.avc-opening-hours-row').each(function (index) {
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
    $('.avc-opening-hours-toggles input[type="checkbox"]').each(function () {
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
    $('.avc-opening-hours-day').each(function () {
      const $select = $(this);
      const value = $select.val();
      $select.find('option').each(function () {
        const $option = $(this);
        const day = $option.val();
        const enabled = toggles.hasOwnProperty(day) ? toggles[day] : true;
        $option.prop('disabled', !enabled);
      });
      if (toggles.hasOwnProperty(value) && !toggles[value]) {
        $select.closest('.avc-opening-hours-row').remove();
      }
    });
    updateOpeningHoursNames($('#avc-opening-hours-rows'));
  }

  function addOpeningRow(dayKey, opens, closes) {
    if (!dayEnabled(dayKey)) {
      return;
    }
    const $container = $('#avc-opening-hours-rows');
    const $row = createOpeningHoursRowTemplate();
    if (!$row) return;
    $row.find('.avc-opening-hours-day').val(dayKey || 'Monday');
    $row.find('.avc-opening-hours-opens').val(opens || '');
    $row.find('.avc-opening-hours-closes').val(closes || '');
    $row.attr('draggable', true);
    $container.append($row);
    applyDayToggleToSelects();
    updateOpeningHoursNames($container);
  }

  function resetOpeningRows(rows) {
    const $container = $('#avc-opening-hours-rows');
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
    $('#avc-opening-hours-rows .avc-opening-hours-row').each(function () {
      const $row = $(this);
      rows.push({
        day_key: normalizeDay($row.find('.avc-opening-hours-day').val()),
        opens: ($row.find('.avc-opening-hours-opens').val() || '').trim(),
        closes: ($row.find('.avc-opening-hours-closes').val() || '').trim(),
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

    $('#avc-oh-template-weekday').on('click', function (e) {
      e.preventDefault();
      resetOpeningRows(templates.weekday);
    });

    $('#avc-oh-template-weekend').on('click', function (e) {
      e.preventDefault();
      $('#avc-include-saturday, #avc-include-sunday, #avc-include-holiday').prop('checked', true);
      resetOpeningRows(templates.weekend);
    });

    $('#avc-oh-template-24h').on('click', function (e) {
      e.preventDefault();
      $('.avc-opening-hours-toggles input[type="checkbox"]').prop('checked', true);
      resetOpeningRows(templates.allDay);
    });

    $('#avc-oh-template-clear').on('click', function (e) {
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
    const $container = $('#avc-opening-hours-rows');
    if (!$container.length) return;
    $('.avc-opening-hours-toggles input[type="checkbox"]').on('change', function () {
      const dayKey = $(this).data('day-key');
      if (!dayKey) {
        return;
      }
      if (dayKey === 'PublicHoliday') {
        $('#avc-holiday-enabled').prop('checked', $(this).is(':checked'));
      } else if (this.id === 'avc-holiday-enabled') {
        $('#avc-include-holiday').prop('checked', $(this).is(':checked'));
      }
      if ($(this).is(':checked')) {
        ensureDayHasSlot(dayKey);
      } else {
        $container.find('.avc-opening-hours-day').each(function () {
          const $select = $(this);
          if ($select.val() === dayKey) {
            $select.closest('.avc-opening-hours-row').remove();
          }
        });
      }
      applyDayToggleToSelects();
      updateOpeningHoursNames($container);
    });
    applyDayToggleToSelects();
  }
  function applyHolidayMode() {
    const enabled = $('#avc-holiday-enabled').is(':checked');
    const mode = $('input[name="ai_search_schema_options[holiday_mode]"]:checked').val() || 'custom';
    if (!enabled) {
      $('#avc-include-holiday').prop('checked', false).trigger('change');
      return;
    }
    $('#avc-include-holiday').prop('checked', true);
    if (mode === 'weekday') {
      const rows = getCurrentOpeningRows().filter(function (row) {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'].includes(row.day_key);
      });
      const holidayRows = rows.map(function (row) {
        return { day_key: 'PublicHoliday', opens: row.opens, closes: row.closes, slots: [{ opens: row.opens, closes: row.closes }] };
      });
      // Remove existing holiday rows and inject weekday copies.
      $('#avc-opening-hours-rows .avc-opening-hours-day').each(function () {
        if ($(this).val() === 'PublicHoliday') {
          $(this).closest('.avc-opening-hours-row').remove();
        }
      });
      resetOpeningRows(holidayRows.concat(getCurrentOpeningRows().filter(function (row) { return row.day_key !== 'PublicHoliday'; })));
    } else if (mode === 'weekend') {
      $('#avc-opening-hours-rows .avc-opening-hours-day').each(function () {
        if ($(this).val() === 'PublicHoliday') {
          $(this).closest('.avc-opening-hours-row').remove();
        }
      });
      addOpeningRow('PublicHoliday', '10:00', '17:00');
    }
  }

  function initOpeningHours() {
    const $container = $('#avc-opening-hours-rows');
    if (!$container.length) {
      return;
    }

    $('#avc-add-opening-hour').on('click', function (event) {
      event.preventDefault();
      addOpeningRow('Monday', '', '');
    });

    $container.on('click', '.avc-remove-opening-hour', function (event) {
      event.preventDefault();
      $(this).closest('.avc-opening-hours-row').remove();
      updateOpeningHoursNames($container);
    });

    addTemplateButtons();
    initDayToggles();
    $('#avc-holiday-enabled').on('change', applyHolidayMode);
    $('input[name="ai_search_schema_options[holiday_mode]"]').on('change', applyHolidayMode);
    initOpeningHoursDrag();
    updateOpeningHoursNames($container);
    applyHolidayMode();
  }

  function initOpeningHoursDrag() {
    const $container = $('#avc-opening-hours-rows');
    if (!$container.length) return;
    let dragSrc = null;

    $container.on('dragstart', '.avc-opening-hours-row', function (e) {
      dragSrc = this;
      $(this).addClass('is-dragging');
      e.originalEvent.dataTransfer.effectAllowed = 'move';
      e.originalEvent.dataTransfer.setData('text/plain', 'drag');
    });

    $container.on('dragend', '.avc-opening-hours-row', function () {
      dragSrc = null;
      $container.find('.avc-opening-hours-row').removeClass('is-dragging drag-over');
      updateOpeningHoursNames($container);
    });

    $container.on('dragover', '.avc-opening-hours-row', function (e) {
      if (!dragSrc || dragSrc === this) return;
      e.preventDefault();
      e.originalEvent.dataTransfer.dropEffect = 'move';
      $(this).addClass('drag-over');
    });

    $container.on('dragleave', '.avc-opening-hours-row', function () {
      $(this).removeClass('drag-over');
    });

    $container.on('drop', '.avc-opening-hours-row', function (e) {
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
    const $button = $('#avc-geocode-button');
    if (!$button.length || !window.aisSettings) {
      return;
    }

    const settings = window.aisSettings;
    const ajaxUrl = settings.ajaxUrl || window.ajaxurl;
    const hasApiKey = !!settings.hasGeocodeApiKey;
    const $status = $('#avc-geocode-status');
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
          $('#avc-address-locality').val(components.address_locality);
        }
        if (components.street_address) {
          $('#avc-address-street').val(components.street_address);
        }
        if (components.postal_code) {
          $('#avc-address-postal').val(components.postal_code);
        }
        if (components.country) {
          $('#avc-address-country').val(components.country);
        }

        if (components.address_region) {
          const $prefecture = $('#avc-address-prefecture');
          const $hiddenRegion = $('#avc-address-region-hidden');
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
            $('#avc-prefecture-iso').text('—');
          }
        }
      }

      const $prefecture = $('#avc-address-prefecture');
      const $regionHidden = $('#avc-address-region-hidden');
      const prefectureValue = $prefecture.length ? ($prefecture.val() || '') : '';
      if ($regionHidden.length) {
        $regionHidden.val(prefectureValue);
      }

      const address = {
        postal_code: $('#avc-address-postal').val(),
        region: $regionHidden.length ? $regionHidden.val() : prefectureValue,
        locality: $('#avc-address-locality').val(),
        street_address: $('#avc-address-street').val(),
        country: $('#avc-address-country').val(),
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
              $('#avc-geo-latitude').val(response.data.latitude);
            }
            if (response.data.longitude) {
              $('#avc-geo-longitude').val(response.data.longitude);
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
    const $clearButton = $('#avc-gmaps-api-key-clear');
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
    const $hero = $('.avc-settings-hero');
    if (!$hero.length) {
      return;
    }
    $hero.find('.notice').each(function () {
      const $notice = $(this);
      $notice.addClass('avc-elevated-notice');
      $notice.insertBefore($hero);
    });
  }

  function initPrefectureSelector() {
    const $prefecture = $('#avc-address-prefecture');
    if (!$prefecture.length) {
      return;
    }
    const $regionHidden = $('#avc-address-region-hidden');
    const $isoOutput = $('#avc-prefecture-iso');

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
    const $postal = $('#avc-address-postal');
    if (!$postal.length) {
      return;
    }

    function fillAddress(result) {
      if (!result) return;
      const prefecture = result.address1 || '';
      const city = result.address2 || '';
      const line = result.address3 || '';

      if (prefecture) {
        const $pref = $('#avc-address-prefecture');
        $pref.val(prefecture).trigger('change');
      }
      if (city) {
        $('#avc-address-city').val(city);
      }
      if (line) {
        $('#avc-address-line').val(line);
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

    $('#avc-zip-autofill').on('click', function (e) {
      e.preventDefault();
      const digits = ($postal.val() || '').replace(/[^0-9]/g, '');
      lookup(digits);
    });
  }

  /**
   * LocalBusiness Google 推奨項目ガイドのリアルタイムチェック
   */
  function updateLocalBusinessGuide() {
    const $guide = $('#avc-lb-guide');
    if (!$guide.length) {
      return;
    }

    // チェック条件の定義
    const checks = {
      name: function() {
        return $.trim($('#avc-company-name').val() || '') !== '';
      },
      address: function() {
        const prefecture = $.trim($('#avc-address-prefecture').val() || '');
        const city = $.trim($('#avc-address-city').val() || '');
        return prefecture !== '' && city !== '';
      },
      phone: function() {
        return $.trim($('#avc-phone').val() || '') !== '';
      },
      geo: function() {
        const lat = $.trim($('#avc-geo-latitude').val() || '');
        const lng = $.trim($('#avc-geo-longitude').val() || '');
        return lat !== '' && lng !== '';
      },
      hours: function() {
        return $('#avc-opening-hours-rows .avc-opening-hours-row').length > 0;
      },
      image: function() {
        const storeImage = $.trim($('#avc-store-image-url').val() || '');
        const lbImage = $.trim($('#avc-lb-image-url').val() || '');
        return storeImage !== '' || lbImage !== '';
      },
      url: function() {
        return $.trim($('#avc-site-url').val() || '') !== '';
      },
      price: function() {
        return $.trim($('#avc-price-range').val() || '') !== '';
      }
    };

    // 各チェック項目を更新
    $.each(checks, function(key, checkFn) {
      const $item = $('#avc-lb-check-' + key).closest('.avc-lb-guide__item');
      const $check = $('#avc-lb-check-' + key);
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
    const $entityType = $('#avc-entity-type');
    const $tooltipText = $('#avc-entity-type-tooltip .avc-field__tooltip-text');

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
    const $guide = $('#avc-lb-guide');
    if (!$guide.length) {
      return;
    }

    // 関連フィールドの変更を監視
    const watchFields = [
      '#avc-company-name',
      '#avc-address-prefecture',
      '#avc-address-city',
      '#avc-address-line',
      '#avc-phone',
      '#avc-geo-latitude',
      '#avc-geo-longitude',
      '#avc-store-image-url',
      '#avc-lb-image-url',
      '#avc-site-url',
      '#avc-price-range'
    ];

    $(watchFields.join(', ')).on('input change', function() {
      updateLocalBusinessGuide();
    });

    // 営業時間行の追加・削除を監視
    const $hoursContainer = $('#avc-opening-hours-rows');
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
      selectButton: '#avc-logo-select',
      removeButton: '#avc-logo-remove',
      urlInput: '#avc-logo-url',
      idInput: '#avc-logo-id',
      preview: '#avc-logo-preview',
    });
    initImageSelector({
      selectButton: '#avc-lb-image-select',
      removeButton: '#avc-lb-image-remove',
      urlInput: '#avc-lb-image-url',
      idInput: '#avc-lb-image-id',
      preview: '#avc-lb-image-preview',
    });
    initImageSelector({
      selectButton: '#avc-store-image-select',
      removeButton: '#avc-store-image-remove',
      urlInput: '#avc-store-image-url',
      idInput: '#avc-store-image-id',
      preview: '#avc-store-image-preview',
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
    const $summary = $('.avc-validation-summary');
    if (!$summary.length) {
      return;
    }

    const $toggle = $summary.find('.avc-validation-summary__toggle');
    const $details = $summary.find('.avc-validation-summary__details');

    // ヘッダークリックでもトグル
    $summary.find('.avc-validation-summary__header').on('click', function (e) {
      // ボタン自体のクリックは二重処理を避ける
      if ($(e.target).closest('.avc-validation-summary__toggle').length) {
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
})(jQuery);
