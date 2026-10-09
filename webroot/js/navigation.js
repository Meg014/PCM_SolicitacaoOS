'use strict';
const menuButton = document.querySelector('.menu-toggle');
const navigation = document.getElementById('main-navigation');
if (menuButton && navigation) {
    const mobile = window.matchMedia('(max-width: 1280px)');
    const update = () => {
        navigation.hidden = mobile.matches;
        menuButton.setAttribute('aria-expanded', String(!navigation.hidden));
    };
    menuButton.addEventListener('click', () => {
        navigation.hidden = !navigation.hidden;
        menuButton.setAttribute('aria-expanded', String(!navigation.hidden));
    });
    mobile.addEventListener('change', update);
    navigation.addEventListener('keydown', event => {
        if (event.key === 'Escape' && mobile.matches) {
            navigation.hidden = true;
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.focus();
        }
    });
    update();
}
