// Applique le theme choisi avant le rendu pour eviter un flash.
// Fichier externe (et non script inline) pour permettre une CSP stricte en production.
(function () {
  var valeur = localStorage.getItem('cja.apparence');
  if (valeur === 'clair' || valeur === 'sombre') {
    document.documentElement.dataset.apparence = valeur;
  }
})();
