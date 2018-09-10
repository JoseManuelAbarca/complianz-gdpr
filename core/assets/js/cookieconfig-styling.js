jQuery(document).ready(function ($) {
    var variation_id = getQueryVariable("variation_id");
    if (!variation_id || variation_id == null) variation_id = '';

    $(document).on('click', "input[name='cmplz_a_b_testing']", function () {
        $("#cookie-settings").submit();
    });

    $(document).on('click', '#cmplz_delete_variation', function () {
        var btn = $(this);
        var delete_variation_id = btn.data('id');
        $.ajax({
            type: "POST",
            url: complianz.url,
            dataType: 'json',
            data: ({
                action: 'cmplz_delete_variation',
                variation_id: delete_variation_id
            }),
            success: function (response) {
                if (response.success) {
                    console.log('rmeove');
                    btn.closest('tr').remove();
                    if (delete_variation_id == variation_id) {
                        $('.cmplz-settings').each(function () {
                            $(this).remove();
                        });
                    }

                }
            }
        });

    });
    var ccName;

    $('.cmplz-color-picker').wpColorPicker({
        change:
            function (event, ui) {
                var container_id = $(event.target).data('hidden-input');
                $('#' + container_id).val(ui.color.toString());
                cmplz_cookie_warning()
            }
        }
    );

    $(document).on('keyup', 'input[name=cmplz_dismiss' + variation_id + ']', function () {
        $(".cc-dismiss").html($(this).val());
    });
    $(document).on('keyup', 'input[name=cmplz_accept' + variation_id + ']', function () {
        $(".cc-allow").html($(this).val());
    });
    $(document).on('keyup', 'input[name=cmplz_revoke' + variation_id + ']', function () {
        $(".cc-revoke").html($(this).val());
    });
    $(document).on('keyup', 'input[name=cmplz_view_preferences' + variation_id + ']', function () {
        $(".cc-revoke").html($(this).val());
    });
    $(document).on('keyup', 'input[name=cmplz_category_functional' + variation_id + ']', function () {
        $(".cc-functional").html($(this).val());
    });
    $(document).on('keyup', 'input[name=cmplz_category_stats' + variation_id + ']', function () {
        $(".cc-stats").html($(this).val());
    });
    $(document).on('keyup', 'input[name=cmplz_category_all' + variation_id + ']', function () {
        $(".cc-all").html($(this).val());
    });
    $(document).on('keyup', 'input[name=cmplz_readmore' + variation_id + ']', function () {
        $(".cc-link").html($(this).val());
    });

    setTimeout(function () {
        for (var i = 0; i < tinymce.editors.length; i++) {
            tinymce.editors[i].on('NodeChange keyup', function (ed, e) {
                var link = $(".cc-message").find('a').html();
                var editor_id = 'cmplz_message' + variation_id;
                var textarea_id = 'cmplz_message' + variation_id;
                if (typeof editor_id == 'undefined') editor_id = wpActiveEditor;
                if (typeof textarea_id == 'undefined') textarea_id = editor_id;

                if (jQuery('#wp-' + editor_id + '-wrap').hasClass('tmce-active') && tinyMCE.get(editor_id)) {
                    var content = tinyMCE.get(editor_id).getContent();
                } else {
                    var content = jQuery('#' + textarea_id).val();
                }
                content = content.replace(/<[\/]{0,1}(p)[^><]*>/ig, "");
                $(".cc-message").html(content + '<a href="#" class="cc-link">' + link + '</a>');
                // Update HTML view textarea (that is the one used to send the data to server).
            });
        }
    }, 1000);

    $(document).on('change', 'select[name=cmplz_static' + variation_id + ']', function () {
        cmplz_cookie_warning();
    });

    $(document).on('change', 'select[name=cmplz_position' + variation_id + ']', function () {
        cmplz_cookie_warning();
    });

    $(document).on('change', 'select[name=cmplz_theme' + variation_id + ']', function () {
        cmplz_cookie_warning();
    });

    $(document).on('keyup', '#cmplz_custom_css' + variation_id + 'editor', function () {
        cmplz_apply_style();
    });

    $(document).on('change', 'input[name=cmplz_use_custom_cookie_css' + variation_id + ']', function () {
        var checked = $('input[name=cmplz_use_custom_cookie_css' + variation_id + ']').is(':checked');
        if (checked){
            cmplz_apply_style();
        } else {
            $("#cmplz-cookie-inline-css").remove();
        }
    });

    function cmplz_apply_style(){
        $('<style id="cmplz-cookie-inline-css">')
            .prop("type", "text/css")
            .html($('textarea[name="cmplz_custom_css' + variation_id + '"]').val()).appendTo("head");
    }

    $(document).on('change', 'input[name=cmplz_use_categories' + variation_id + ']', function () {
        cmplz_cookie_warning();
    });

    cmplz_cookie_warning();

    function cmplz_cookie_warning() {

        if (ccName) {
            ccName.fadeOut();
            ccName.destroy();
        }
        var ccCategories = $('input[name=cmplz_use_categories' + variation_id + ']').is(':checked');
        var ccDismiss = $('input[name=cmplz_dismiss' + variation_id + ']').val();
        var ccMessage = $('textarea[name=cmplz_message' + variation_id + ']').val();
        var ccAllow = $('input[name=cmplz_accept' + variation_id + ']').val();
        var ccLink = $('input[name=cmplz_readmore' + variation_id + ']').val();
        var ccStatic = false;
        var ccBorder = $('input[name=cmplz_border_color' + variation_id + ']').val();
        var ccPosition = $('select[name=cmplz_position' + variation_id + ']').val();
        var ccType = 'opt-in';
        var ccTheme = $('select[name=cmplz_theme' + variation_id + ']').val();

        var ccLayout = 'basic';
        var ccCheckboxAll = '<input type="checkbox" id="cmplz_all" style="display: none;"><label for="cmplz_all" class="cc-check"><svg width="18px" height="18px" viewBox="0 0 18 18"> <path d="M1,9 L1,3.5 C1,2 2,1 3.5,1 L14.5,1 C16,1 17,2 17,3.5 L17,14.5 C17,16 16,17 14.5,17 L3.5,17 C2,17 1,16 1,14.5 L1,9 Z"></path> <polyline points="1 9 7 14 15 4"></polyline></svg></label>';
        var ccCheckboxFunctional = ccCheckboxAll.replace('type', 'checked disabled type');
        ccCheckboxFunctional = ccCheckboxFunctional.replace(/cmplz_all/g, 'cmplz_functional');
        var ccPopupTextColor = $('input[name=cmplz_popup_text_color' + variation_id + ']').val();
        var ccButtonBackgroundColor = $('input[name=cmplz_button_background_color' + variation_id + ']').val();
        var ccButtonTextColor = $('input[name=cmplz_button_text_color' + variation_id + ']').val();
        var ccSavePreferences = $('input[name=cmplz_save_preferences' + variation_id + ']').val();
        var ccViewPreferences = $('input[name=cmplz_view_preferences' + variation_id + ']').val();
        var ccCategoryStats = $('input[name=cmplz_category_stats' + variation_id + ']').val();
        var ccRevokeText = $('input[name=cmplz_revoke' + variation_id + ']').val();
        var ccHasStatsCategory = $('input[name=cmplz_cookie_warning_required_stats]').val();
        var ccCategoryFunctional = $('input[name=cmplz_category_functional' + variation_id + ']').val();
        var ccCategoryAll = $('input[name=cmplz_category_all' + variation_id + ']').val();

        if (ccCategories) {
            ccType = 'categories';
            ccLayout = 'categories-layout';
            ccRevokeText =ccViewPreferences;
            var ccCheckboxStats = '';
            if (ccHasStatsCategory){
                ccCheckboxStats = '<label>' + ccCheckboxAll + '<span class="cc-stats">'+ccCategoryStats+'</span></label>';
            }
        }

        if (ccPosition === 'static') {
            ccStatic = true;
            ccPosition = 'top';
        }

        if (ccTheme === 'edgeless') {
            ccBorder = false;
        }

        var ccStatus;
        window.cookieconsent.initialise({
            cookie: {
                name: 'complianz_config',
                expiryDays: 1
            },
            "revokeBtn": '<div class="cc-revoke cc-bottom {{classes}}">' + ccRevokeText + '</div>',
            "palette": {
                "popup": {
                    "background": $('input[name=cmplz_popup_background_color' + variation_id + ']').val(),
                    "text": ccPopupTextColor,
                },
                "button": {
                    "background": ccButtonBackgroundColor,
                    "text": ccButtonTextColor,
                    "border": ccBorder
                }
            },
            "layout": ccLayout,
            "layouts": {
                'categories-layout': '{{messagelink}}{{categories-checkboxes}}{{compliance}}',
            },
            "elements": {
                "categories-checkboxes": '<label>' + ccCheckboxFunctional + '<span class="cc-functional">{{categoryfunctional}}</span></label>'+ccCheckboxStats+'<label>' + ccCheckboxAll + '<span class="cc-all">{{categoryall}}</span></label>',
                "save": '<a aria-label="save cookies" tabindex="0" class="cc-btn cc-save">{{save_preferences}}</a>',
            },
            "type": ccType,
            "compliance": {
                'categories': '<div class="cc-compliance cc-highlight">{{save}}</div>',
            },
            "theme": ccTheme,
            "static": ccStatic,
            "position": ccPosition,
            "content": {
                "save_preferences" : ccSavePreferences,
                "message": ccMessage,
                "dismiss": ccDismiss,
                "allow": ccAllow,
                "link": ccLink,
                "href": '#',
                "categoryfunctional": ccCategoryFunctional,
                "categoryall": ccCategoryAll
            },
            onInitialise: function (status) {
                ccStatus = status;
            }
        }, function (popup) {
            ccName = popup;
            ccName.open();
            if ($('#cmplz_all').length) {
                if (ccStatus === 'allow') $('#cmplz_all').prop('checked', true);
                $('#cmplz_functional:checked + .cc-check svg').css({"stroke": ccPopupTextColor});
                $('#cmplz_all:checked + .cc-check svg').css({"stroke": ccPopupTextColor});
                $('.cc-save').css({"border-color" : ccBorder, "background-color": ccButtonBackgroundColor, "color" : ccButtonTextColor});
                $('.cc-check svg').css({"stroke": ccPopupTextColor});
            }

        });

        $(document).on('click', '.cc-save', function(){
            if ($('#cmplz_all').is(":checked")) {
                ccName.setStatus(cookieconsent.status.allow);
            } else {
                ccName.setStatus(cookieconsent.status.dismiss);
            }
            ccName.close();
            $('.cc-revoke').fadeIn();
        });

        if (ccPosition === 'top') {
            $('.cc-window').css({'top': '30px'});
        }

        //make it float over the wp menu
        if (ccPosition === 'static') {
            $('.cc-grower').css('z-index', 10);
            $('.cc-grower').css('position', 'relative');
        }
    }

    function getQueryVariable(variable) {
        var query = window.location.search.substring(1);
        var vars = query.split("&");
        for (var i = 0; i < vars.length; i++) {
            var pair = vars[i].split("=");
            if (pair[0] == variable) {
                return pair[1];
            }
        }

        return false;
    }

});