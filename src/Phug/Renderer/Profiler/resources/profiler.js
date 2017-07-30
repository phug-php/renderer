(function () {

    function each(list, callback) {
        for (var i = 0; i < list.length; i++) {
            callback(list[i]);
        }
    }
    function info(html) {
        each(document.getElementsByClassName('profiler-info'), function (info) {
            each(info.getElementsByTagName('pre'), function (pre) {
                pre.innerHTML = html;
            });
            each(info.getElementsByClassName('profiler-info-close'), function (close) {
                close.style.display = html ? 'block' : 'none';
            });
        });
    }
    document.addEventListener('click',  function (event) {
        if (~event.target.className.indexOf('profiler-bar')) {
            var current = event.target.getAttribute('data-event');
            var previous = event.target.getAttribute('data-previous');
            info("Current event: " + current + "\n\nPrevious event: " + previous);
        } else if (~event.target.className.indexOf('profiler-info-close')) {
            info('');
        }
    });
    document.addEventListener('click',  function (event) {
        if (~event.target.className.indexOf('profiler-bar')) {
            var current = event.target.getAttribute('data-event');
            var previous = event.target.getAttribute('data-previous');
            info("Current event: " + current + "\n\nPrevious event: " + previous);
        } else if (~event.target.className.indexOf('profiler-info-close')) {
            info('');
        }
    });
    each(document.getElementsByClassName('profiler-reduce'), function (reduce) {
        reduce.addEventListener('click',  function () {
            var wrapper = reduce.parentNode.parentNode;
            var className = ' ' + wrapper.className + ' ';
            var regExp = /\sclosed\s/g;
            if (regExp.test(className)) {
                wrapper.className = className.replace(regExp, ' ');

                return;
            }

            wrapper.className += ' closed';
        });
    });
    var rank = 100;
    var zoom = 100;
    document.addEventListener('mousewheel', function (event) {
        if (event.shiftKey) {
            rank = Math.max(90, rank - event.deltaY / 10);
            var newZoom = Math.max(90, rank * rank / 100);
            each(document.getElementsByClassName('profiler'), function (profiler) {
                profiler.style.width = newZoom + '%';
            });
            var timelinePosition = event.pageX / document.body.scrollWidth;
            var newPageX = event.pageX * newZoom / zoom;
            document.body.scrollLeft = newPageX - event.clientX;
            zoom = newZoom;
            event.preventDefault();
        }
    });

})();
