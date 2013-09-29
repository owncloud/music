angular.module("Music").run(['gettextCatalog', function (gettextCatalog) {
    gettextCatalog.setStrings('de', {"Loading ...":"Lade ...","Previous":"Vorheriges","Play":"Wiedergeben","Pause":"Pausieren","Next":"Nächstes","Shuffle":"Zufallswiedergabe","Repeat":"Wiederholen","Delete":"Löschen","Nothing in here. Upload your music!":"Nichts da. Lade deine Musik hoch!","Show less ...":"Zeige weniger ...","Show all [[ trackcount ]] songs ...":["Zeige alle [[ trackcount ]] Titel ...","Zeige alle [[ trackcount ]] Titel ..."]});

}]);