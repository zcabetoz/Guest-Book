angular.module("ngApp", [])
    .directive('backImg', function () {
        return function (scope, element, attrs) {
            attrs.$observe('backImg', function (value) {
                element.css({
                    "background": "url(" + value + ")", "background-size": "cover", "background-position": "center"
                });
            });
        }
    });