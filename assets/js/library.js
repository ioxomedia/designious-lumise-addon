var isDesigniousLibraryImagesLoaded = false;
var isDesigniousLibraryClipartsLoaded = false;

function lumise_addon_designiousLibrary(lumise) {
    $('#designious-data-nav').off('click').on('click', function(e) {
        if (! isDesigniousLibraryImagesLoaded) {
            lumise.ops['designious_loading'] = true;
            lumise.xitems.load('designious', {
                click: function (op, el) {
                    debugger;
                    if (lumise.xitems.resources['designious'].url[op.id]) {
                        op.url = lumise.xitems.resources['designious'].url[op.id].replace(lumise.data.upload_url, '');
                        lumise.fn.preset_import([op], el, {});
                    }
                }
            });
            isDesigniousLibraryImagesLoaded = true;
        }

        var wrp = $(this).closest('#lumise-uploads'),
            tab = $('#lumise-designious-library');

        wrp.find('header button.active, div[data-tab].active').removeClass('active');

        $(this).addClass('active');
        tab.addClass('active');

        e.preventDefault();
    });

    var initialClipartsLoadMethod = lumise.design.nav.load.cliparts;

    lumise.design.nav.load.cliparts = function(e) {
        initialClipartsLoadMethod();
        $('#lumise-cliparts>header>button[data-cliparts-nav]').off('click').on('click', function(e) {
            if (! isDesigniousLibraryClipartsLoaded) {
                lumise.ops['designious-cliparts_loading'] = true;
                lumise.xitems.load('designious-cliparts', {
                    click: function (op, el) {
                        debugger;
                        if (lumise.xitems.resources['designious-cliparts'].url[op.id]) {
                            op.url = lumise.xitems.resources['designious-cliparts'].url[op.id].replace(lumise.data.upload_url, '');
                            lumise.fn.preset_import([op], el, {});
                        }
                    }
                });
                isDesigniousLibraryClipartsLoaded = true;
            }
            var wrp = $(this).closest('#lumise-cliparts'),
                nav = this.getAttribute('data-cliparts-nav'),
                tab = wrp.find('div[data-cliparts-tab="'+nav+'"]');

            wrp.find('header button.active, div[data-cliparts-tab].active').removeClass('active');

            $(this).addClass('active');
            tab.addClass('active');

            e.preventDefault();

        });
    };
}