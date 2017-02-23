function __ (key, values) {
    values = values || [];

    if (typeof taxonomyEditorTranslations !== 'undefined' && taxonomyEditorTranslations[key]) {
        var translation = taxonomyEditorTranslations[key];

        if (values.length > 0) {
            values.forEach(function (val, index) {
                translation = translation.replace('%' + (index + 1) + '%', val);
            });
        }
        return translation;
    }

    return key;
}

function convertToSlug (Text) {
    return Text
        .toLowerCase()
        .replace(/ /g, '-')
        .replace(/[^\w-]+/g, '');
}

$().ready(function () {
    var ns = $('.sortable').nestedSortable({
        attribute            : 'id',
        forcePlaceholderSize : true,
        handle               : '.itemTitle',
        helper               : 'clone',
        items                : 'li',
        opacity              : .6,
        placeholder          : 'placeholder',
        revert               : 250,
        tabSize              : 25,
        tolerance            : 'pointer',
        toleranceElement     : '> div',
        maxLevels            : 1,
        isTree               : true,
        expandOnHover        : 700,
        startCollapsed       : true
    });

    function registerEvents () {
        $('.expandEditor, .itemTitle, .disclose, .deleteTaxonomy').off('click');

        $('.disclose').on('click', function () {
            $(this).closest('li').toggleClass('mjs-nestedSortable-collapsed').toggleClass('mjs-nestedSortable-expanded');
            $(this).find('i').toggleClass('fa-minus').toggleClass('fa-plus');
        });

        $('.expandEditor, .itemTitle').on('click', function () {
            $(this).parent().siblings('.editor').toggle();
            $(this).parent().find('.expandEditor i').toggleClass('fa-chevron-down').toggleClass('fa-chevron-up');
        });

        $('.deleteTaxonomy').on('click', function () {
            $(this).parents('.mjs-nestedSortable-expanded').first().remove();
        });

        $('.editor [type=text]').on('input', function () {
            var key = $(this).attr('name');

            $(this).parents('.mjs-nestedSortable-expanded').first().attr('data-' + key, $(this).val());

            if (key === 'name') {
                $(this).parents('.mjs-nestedSortable-expanded').first().find('.itemTitle').first().text($(this).val());
            }
        });

        $('.editor [type=checkbox]').on('change', function () {
            var key = $(this).attr('name');

            $(this).parents('.mjs-nestedSortable-expanded').first().attr('data-' + key, $(this).prop('checked'));
        });

        $('.editor select').on('change', function () {
            var key = $(this).attr('name');

            $(this).parents('.mjs-nestedSortable-expanded').first().attr('data-' + key, $(this).val());
        });

        // Auto generate slugs
        $('.js-add-new-name').on('input', function (e) {
            $('.js-add-new-slug').val(convertToSlug($(this).val()));
        });
    }

    registerEvents();

    /**
     * Add new taxonomy terms
     */
    $('.js-add-new-term').on('click', function (e) {
        'use strict';

        e.preventDefault();

        var slug = $(this).closest('.js-add-new-form').find('.js-add-new-slug').val();
        var name = $(this).closest('.js-add-new-form').find('.js-add-new-name').val();

        if (slug.length && name.length) {
            addToActiveTaxonomy(slug, name);
        } else {
            alert('Please enter both slug taxonomy term name and slug');
        }
    });

    /**
     * Form Submission
     * Prepare the form values for processing
     */
    $('.js-saveform').submit(function (e) {
        var taxonomies = {};

        $('.js-taxonomies .tab-pane').each(function (elem) {
            var taxonomy                   = $(this).find('ol.sortable').nestedSortable('toHierarchy', {startDepthCount : 0});
            taxonomy                       = clean(taxonomy);
            taxonomies[$(this).attr('id')] = taxonomy;
        });

        $('.js-saveform [name="taxonomies"]').attr('value', JSON.stringify(taxonomies));
    });

    /**
     * Clean data so we avoid circular structures in JSON.stringify
     */
    function clean (arr) {
        arr.forEach(function (item) {
            delete item['nestedSortable-item'];
            delete item.nestedSortableItem;
            delete item.id;
        });

        return arr;
    }

    function addToActiveTaxonomy (slug, name) {
        var markup = '\
        <li class="mjs-nestedSortable-expanded" id="taxonomyterm-' + slug + '" data-slug="' + slug + '" data-name="' + name + '"> \
            <div> \
                <div class="flex-row"> \
                <span title="' + __("taxonomyeditor.action.showhideeditor") + '" class="no-grow expandEditor"><i class="fa fa-chevron-up" aria-hidden="true"></i></span> \
                    <span class="itemTitle">' + name + '</span> \
                    <span title="' + __("taxonomyeditor.action.delete") + '" class="no-grow deleteTaxonomy"><i class="fa fa-trash-o" aria-hidden="true"></i></span> \
                </div> \
                <div class="form-horizontal editor"> \
                    <div class="form-group"> \
                        <label class="col-sm-3 control-label">' + __("taxonomyeditor.fields.name") + '</label> \
                        <div class="col-sm-9"> \
                            <input type="text" class="form-control" placeholder="' + __("taxonomyeditor.fields.name") + '" name="' + __("taxonomyeditor.fields.name") + '" value="' + name + '"> \
                        </div> \
                    </div> \
                    <div class="form-group"> \
                        <label class="col-sm-3 control-label">' + __("taxonomyeditor.fields.slug") + '</label> \
                        <div class="col-sm-9"> \
                            <input type="text" class="form-control" placeholder="' + __("taxonomyeditor.fields.slug") + '" name="' + __("taxonomyeditor.fields.slug") + '" value="' + slug + '"> \
                        </div> \
                    </div> \
                </div> \
            </div> \
        </li>';

        $('.active ol.sortable').append(markup);

        registerEvents();
    }
});
