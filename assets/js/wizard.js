/**
 * AI Search Schema - Setup Wizard JavaScript
 *
 * @package Aivec\AiSearchSchema
 */

/* global jQuery, wp, aisWizardData */

(function ($) {
  'use strict';

  const Wizard = {
    /**
     * Initialize wizard.
     */
    init: function () {
      this.bindEvents();
      this.initTypeSelection();
      this.initHoursTable();
      this.initImportModal();
      this.initLogoUpload();
      this.initGeocoding();
      this.initSchemaPreview();
    },

    /**
     * Bind global events.
     */
    bindEvents: function () {
      // Next button click
      $(document).on('click', '.avc-wizard-next-btn', this.handleNextClick.bind(this));

      // Language switcher
      $(document).on('click', '.avc-wizard-lang-btn', this.handleLanguageSwitch.bind(this));
    },

    /**
     * Handle next button click.
     *
     * @param {Event} e Click event.
     */
    handleNextClick: function (e) {
      e.preventDefault();

      const $btn = $(e.currentTarget);
      const nextStep = $btn.data('next');

      if (!nextStep) {
        return;
      }

      // Validate current step
      if (!this.validateCurrentStep()) {
        return;
      }

      // Collect form data
      const formData = this.collectFormData();

      // Save progress via AJAX
      this.saveProgress(formData, nextStep);
    },

    /**
     * Validate current step.
     *
     * @return {boolean} True if valid.
     */
    validateCurrentStep: function () {
      let isValid = true;
      const $step = $('.avc-wizard-step');

      // Check required fields
      $step.find('input[required], select[required], textarea[required]').each(function () {
        const $field = $(this);
        if (!$field.val()) {
          isValid = false;
          $field.addClass('avc-wizard-form__input--error');
          $field.one('input change', function () {
            $(this).removeClass('avc-wizard-form__input--error');
          });
        }
      });

      // Check type selection (step 2)
      if ($step.hasClass('avc-wizard-step--type')) {
        const selectedType = $('input[name="entity_type"]:checked').val();
        if (!selectedType) {
          isValid = false;
          $('.avc-wizard-type-grid').addClass('avc-wizard-type-grid--error');
        }
      }

      return isValid;
    },

    /**
     * Collect form data from current step.
     *
     * @return {Object} Form data.
     */
    collectFormData: function () {
      const data = {};

      $('.avc-wizard-step').find('input, select, textarea').each(function () {
        const $field = $(this);
        const name = $field.attr('name');

        if (!name) {
          return;
        }

        if ($field.is(':checkbox')) {
          data[name] = $field.is(':checked') ? '1' : '0';
        } else if ($field.is(':radio')) {
          if ($field.is(':checked')) {
            data[name] = $field.val();
          }
        } else {
          data[name] = $field.val();
        }
      });

      return data;
    },

    /**
     * Save progress via AJAX.
     *
     * @param {Object} formData Form data.
     * @param {string} nextStep Next step slug.
     */
    saveProgress: function (formData, nextStep) {
      const $btn = $('.avc-wizard-next-btn');
      $btn.prop('disabled', true);

      $.ajax({
        url: aisWizardData.ajaxUrl,
        method: 'POST',
        data: {
          action: 'ai_search_schema_wizard_save_step',
          nonce: aisWizardData.nonce,
          step: aisWizardData.currentStep,
          data: formData
        },
        success: function (response) {
          if (response.success) {
            // Navigate to next step
            window.location.href = aisWizardData.wizardUrl + '&step=' + nextStep;
          } else {
            alert(response.data.message || aisWizardData.strings.errorSaving);
            $btn.prop('disabled', false);
          }
        },
        error: function () {
          alert(aisWizardData.strings.errorSaving);
          $btn.prop('disabled', false);
        }
      });
    },

    /**
     * Handle language switch.
     *
     * @param {Event} e Click event.
     */
    handleLanguageSwitch: function (e) {
      e.preventDefault();

      const $btn = $(e.currentTarget);
      const locale = $btn.data('locale');

      if (!locale) {
        return;
      }

      // Update URL with locale parameter
      const url = new URL(window.location.href);
      url.searchParams.set('locale', locale);
      window.location.href = url.toString();
    },

    /**
     * Initialize type selection (step 2).
     */
    initTypeSelection: function () {
      const $typeGrid = $('.avc-wizard-type-grid');
      if (!$typeGrid.length) {
        return;
      }

      const $nextBtn = $('#avc-wizard-type-next');

      // Type card click
      $typeGrid.on('click', '.avc-wizard-type-card', function () {
        const $card = $(this);
        const $radio = $card.find('input[type="radio"]');

        // Update selection
        $('.avc-wizard-type-card').removeClass('avc-wizard-type-card--selected');
        $card.addClass('avc-wizard-type-card--selected');
        $radio.prop('checked', true);

        // Enable next button
        $nextBtn.prop('disabled', false);

        // Update info box
        Wizard.updateTypeInfo($radio.val());
      });

      // Check initial selection
      const $selectedType = $('input[name="entity_type"]:checked');
      if ($selectedType.length) {
        $nextBtn.prop('disabled', false);
        this.updateTypeInfo($selectedType.val());
      }
    },

    /**
     * Update type info box.
     *
     * @param {string} type Selected type.
     */
    updateTypeInfo: function (type) {
      const $infoBox = $('#avc-wizard-type-info');
      const $title = $('#avc-wizard-type-info-title');
      const $text = $('#avc-wizard-type-info-text');

      const typeInfo = {
        LocalBusiness: {
          title: aisWizardData.strings.localBusinessTitle || 'Local Business Selected',
          text: aisWizardData.strings.localBusinessText || 'You will be able to enter your business address, hours, and contact information.'
        },
        Organization: {
          title: aisWizardData.strings.organizationTitle || 'Organization Selected',
          text: aisWizardData.strings.organizationText || 'Perfect for companies, non-profits, and institutions.'
        },
        Person: {
          title: aisWizardData.strings.personTitle || 'Personal Site Selected',
          text: aisWizardData.strings.personText || 'Great for blogs, portfolios, and freelancer websites.'
        },
        WebSite: {
          title: aisWizardData.strings.websiteTitle || 'Online Service Selected',
          text: aisWizardData.strings.websiteText || 'Ideal for news sites, web apps, and online tools.'
        }
      };

      const info = typeInfo[type];
      if (info) {
        $title.text(info.title);
        $text.text(info.text);
        $infoBox.slideDown(200);
      } else {
        $infoBox.slideUp(200);
      }
    },

    /**
     * Initialize hours table (step 4).
     */
    initHoursTable: function () {
      const $hoursTable = $('.avc-wizard-hours-table');
      if (!$hoursTable.length) {
        return;
      }

      // Toggle day open/closed
      $hoursTable.on('change', '.avc-wizard-hours-toggle', function () {
        const $toggle = $(this);
        const $row = $toggle.closest('.avc-wizard-hours-table__row');
        const $selects = $row.find('.avc-wizard-hours-select');

        if ($toggle.is(':checked')) {
          $selects.prop('disabled', false);
          // Set default hours if empty
          $selects.each(function () {
            const $select = $(this);
            if (!$select.val()) {
              const name = $select.attr('name');
              if (name && name.includes('_opens')) {
                $select.val('09:00');
              } else if (name && name.includes('_closes')) {
                $select.val('18:00');
              }
            }
          });
        } else {
          $selects.prop('disabled', true);
        }

        Wizard.updateHoursPreview();
      });

      // Time select change
      $hoursTable.on('change', '.avc-wizard-hours-select', function () {
        Wizard.updateHoursPreview();
      });

      // Quick setup: Same hours every weekday
      $('#avc-wizard-hours-weekdays').on('click', function () {
        Wizard.setHoursForDays(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], '09:00', '18:00');
      });

      // Quick setup: Same hours every day
      $('#avc-wizard-hours-everyday').on('click', function () {
        Wizard.setHoursForDays(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], '09:00', '18:00');
      });

      // Quick setup: Clear all
      $('#avc-wizard-hours-clear').on('click', function () {
        $('.avc-wizard-hours-table__row').each(function () {
          const $row = $(this);
          $row.find('.avc-wizard-hours-toggle').prop('checked', false);
          $row.find('.avc-wizard-hours-select').prop('disabled', true).val('');
        });
        Wizard.updateHoursPreview();
      });

      // Initial preview
      this.updateHoursPreview();
    },

    /**
     * Set hours for specific days.
     *
     * @param {Array}  days   Array of day keys.
     * @param {string} opens  Opening time.
     * @param {string} closes Closing time.
     */
    setHoursForDays: function (days, opens, closes) {
      days.forEach(function (day) {
        const $row = $(`.avc-wizard-hours-table__row[data-day="${day}"]`);
        if ($row.length) {
          $row.find('.avc-wizard-hours-toggle').prop('checked', true);
          $row.find('.avc-wizard-hours-select').prop('disabled', false);
          $row.find(`select[name="hours_${day}_opens"]`).val(opens);
          $row.find(`select[name="hours_${day}_closes"]`).val(closes);
        }
      });
      this.updateHoursPreview();
    },

    /**
     * Update hours preview.
     */
    updateHoursPreview: function () {
      const $preview = $('#avc-wizard-hours-preview');
      const days = [];
      const dayNames = {
        monday: aisWizardData.strings.monday || 'Monday',
        tuesday: aisWizardData.strings.tuesday || 'Tuesday',
        wednesday: aisWizardData.strings.wednesday || 'Wednesday',
        thursday: aisWizardData.strings.thursday || 'Thursday',
        friday: aisWizardData.strings.friday || 'Friday',
        saturday: aisWizardData.strings.saturday || 'Saturday',
        sunday: aisWizardData.strings.sunday || 'Sunday'
      };

      $('.avc-wizard-hours-table__row').each(function () {
        const $row = $(this);
        const day = $row.data('day');
        const isOpen = $row.find('.avc-wizard-hours-toggle').is(':checked');

        if (isOpen) {
          const opens = $row.find(`select[name="hours_${day}_opens"]`).val();
          const closes = $row.find(`select[name="hours_${day}_closes"]`).val();
          if (opens && closes) {
            days.push({
              name: dayNames[day] || day,
              hours: `${opens} - ${closes}`
            });
          }
        }
      });

      if (days.length === 0) {
        $preview.html('<p class="avc-wizard-hours-preview__empty">' + (aisWizardData.strings.noHoursSet || 'Set your business hours above to see a preview.') + '</p>');
      } else {
        let html = '<ul class="avc-wizard-hours-preview__list">';
        days.forEach(function (day) {
          html += `<li><strong>${day.name}:</strong> ${day.hours}</li>`;
        });
        html += '</ul>';
        $preview.html(html);
      }
    },

    /**
     * Initialize import modal.
     */
    initImportModal: function () {
      const $modal = $('#avc-wizard-import-modal');
      if (!$modal.length) {
        return;
      }

      // Show modal
      $('#avc-wizard-show-import').on('click', function () {
        $modal.fadeIn(200);
      });

      // Close modal
      $modal.on('click', '.avc-wizard-modal__close, .avc-wizard-modal__backdrop', function () {
        $modal.fadeOut(200);
      });

      // Import button
      $modal.on('click', '.avc-wizard-import-btn', function () {
        const $btn = $(this);
        const source = $btn.data('source');

        if (!source) {
          return;
        }

        $btn.prop('disabled', true).text(aisWizardData.strings.importing || 'Importing...');

        $.ajax({
          url: aisWizardData.ajaxUrl,
          method: 'POST',
          data: {
            action: 'ai_search_schema_wizard_import',
            nonce: aisWizardData.nonce,
            source: source
          },
          success: function (response) {
            if (response.success) {
              $btn.text(aisWizardData.strings.imported || 'Imported!');
              setTimeout(function () {
                window.location.href = aisWizardData.wizardUrl + '&step=basics';
              }, 1000);
            } else {
              alert(response.data.message || aisWizardData.strings.importError || 'Import failed.');
              $btn.prop('disabled', false).text(aisWizardData.strings.import || 'Import');
            }
          },
          error: function () {
            alert(aisWizardData.strings.importError || 'Import failed.');
            $btn.prop('disabled', false).text(aisWizardData.strings.import || 'Import');
          }
        });
      });
    },

    /**
     * Initialize logo upload.
     */
    initLogoUpload: function () {
      const $uploadBtn = $('#avc-wizard-upload-logo');
      const $removeBtn = $('#avc-wizard-remove-logo');
      const $preview = $('#avc-wizard-logo-preview');
      const $input = $('#avc-wizard-logo-url');

      if (!$uploadBtn.length) {
        return;
      }

      // Upload button click
      $uploadBtn.on('click', function () {
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
          alert('WordPress media library is not available.');
          return;
        }

        const frame = wp.media({
          title: aisWizardData.strings.selectLogo || 'Select Logo',
          button: {
            text: aisWizardData.strings.useLogo || 'Use this logo'
          },
          multiple: false,
          library: {
            type: 'image'
          }
        });

        frame.on('select', function () {
          const attachment = frame.state().get('selection').first().toJSON();
          $input.val(attachment.url);
          $preview.html('<img src="' + attachment.url + '" alt="Logo preview">');
          $removeBtn.show();
        });

        frame.open();
      });

      // Remove button click
      $removeBtn.on('click', function () {
        $input.val('');
        $preview.html('<div class="avc-wizard-logo-upload__placeholder"><svg viewBox="0 0 24 24" width="48" height="48" fill="#9ca3af"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg><span>' + (aisWizardData.strings.noLogo || 'No logo selected') + '</span></div>');
        $removeBtn.hide();
      });
    },

    /**
     * Initialize geocoding.
     */
    initGeocoding: function () {
      const $geocodeBtn = $('#avc-wizard-geocode-btn');
      if (!$geocodeBtn.length) {
        return;
      }

      $geocodeBtn.on('click', function () {
        const address = [
          $('#avc-wizard-postal-code').val(),
          $('#avc-wizard-region').val(),
          $('#avc-wizard-locality').val(),
          $('#avc-wizard-street').val()
        ].filter(Boolean).join(' ');

        if (!address) {
          alert(aisWizardData.strings.enterAddress || 'Please enter an address first.');
          return;
        }

        $geocodeBtn.prop('disabled', true).text(aisWizardData.strings.fetching || 'Fetching...');

        $.ajax({
          url: aisWizardData.ajaxUrl,
          method: 'POST',
          data: {
            action: 'ai_search_schema_geocode',
            nonce: aisWizardData.geocodeNonce || aisWizardData.nonce,
            address: address
          },
          success: function (response) {
            if (response.success && response.data) {
              $('#avc-wizard-lat').val(response.data.lat);
              $('#avc-wizard-lng').val(response.data.lng);
            } else {
              alert(response.data.message || aisWizardData.strings.geocodeError || 'Could not fetch coordinates.');
            }
            $geocodeBtn.prop('disabled', false).html('<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>' + (aisWizardData.strings.getCoordinates || 'Get Coordinates'));
          },
          error: function () {
            alert(aisWizardData.strings.geocodeError || 'Could not fetch coordinates.');
            $geocodeBtn.prop('disabled', false).html('<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>' + (aisWizardData.strings.getCoordinates || 'Get Coordinates'));
          }
        });
      });
    },

    /**
     * Initialize schema preview (complete step).
     */
    initSchemaPreview: function () {
      const $showBtn = $('#avc-wizard-show-schema');
      const $code = $('#avc-wizard-schema-code');

      if (!$showBtn.length) {
        return;
      }

      $showBtn.on('click', function () {
        if ($code.is(':visible')) {
          $code.slideUp(200);
          $showBtn.text(aisWizardData.strings.viewSchema || 'View JSON-LD Schema');
        } else {
          // Fetch schema
          $.ajax({
            url: aisWizardData.ajaxUrl,
            method: 'POST',
            data: {
              action: 'ai_search_schema_wizard_get_schema',
              nonce: aisWizardData.nonce
            },
            success: function (response) {
              if (response.success && response.data.schema) {
                $code.text(JSON.stringify(response.data.schema, null, 2)).slideDown(200);
                $showBtn.text(aisWizardData.strings.hideSchema || 'Hide JSON-LD Schema');
              } else {
                $code.text(aisWizardData.strings.noSchema || 'No schema generated yet.').slideDown(200);
              }
            },
            error: function () {
              $code.text(aisWizardData.strings.schemaError || 'Could not load schema.').slideDown(200);
            }
          });
        }
      });
    }
  };

  // Initialize on document ready
  $(document).ready(function () {
    Wizard.init();
  });
})(jQuery);
