<?php

return array(
    // Plugin
    'Gantt charts for Kanboard powered by the Frappe Gantt library' => 'Diagrammes de Gantt pour Kanboard, propulsés par la bibliothèque Frappe Gantt',

    // Navigation
    'Gantt' => 'Gantt',
    'Gantt chart' => 'Diagramme de Gantt',
    'Gantt chart for all projects' => 'Diagramme de Gantt pour tous les projets',
    'My Gantt chart' => 'Mon diagramme de Gantt',
    'Gantt settings' => 'Paramètres du Gantt',
    'Sort by position' => 'Trier par position',
    'Sort by date' => 'Trier par date',
    'Add task' => 'Ajouter une tâche',

    // Empty states and notices
    'There is no task in your project.' => 'Il n\'y a aucune tâche dans votre projet.',
    'There is no task assigned to you.' => 'Aucune tâche ne vous est assignée.',
    'There is no project.' => 'Il n\'y a aucun projet.',
    'Moving or resizing a bar changes the start date and the due date of the task. Dragging the progress handle changes the completion percentage.' => 'Déplacer ou redimensionner une barre modifie la date de début et la date d\'échéance de la tâche. Déplacer la poignée de progression modifie le pourcentage d\'avancement.',
    'Moving or resizing a bar changes the start date and the end date of the project.' => 'Déplacer ou redimensionner une barre modifie la date de début et la date de fin du projet.',
    'This chart is read-only. Open a project to change the dates of its tasks.' => 'Ce diagramme est en lecture seule. Ouvrez un projet pour modifier les dates de ses tâches.',
    'You are not allowed to update tasks in this project.' => 'Vous n\'êtes pas autorisé à modifier les tâches de ce projet.',
    'Powered by Frappe Gantt %s' => 'Propulsé par Frappe Gantt %s',

    // Popup
    'Not defined' => 'Non définie',
    'This task has no start date or due date.' => 'Cette tâche n\'a ni date de début ni date d\'échéance.',
    'Open the board' => 'Ouvrir le tableau',
    'Open the Gantt chart' => 'Ouvrir le diagramme de Gantt',
    'Progress' => 'Avancement',
    'Duration' => 'Durée',
    'day' => 'jour',
    'Saved' => 'Enregistré',
    'Unable to save this change' => 'Impossible d\'enregistrer cette modification',
    'Subtasks have no dates of their own and cannot be moved.' => 'Les sous-tâches n\'ont pas de dates propres et ne peuvent pas être déplacées.',

    // Settings: groups
    'Layout' => 'Disposition',
    'Timeline' => 'Frise chronologique',
    'Weekends and holidays' => 'Week-ends et jours fériés',
    'Interaction' => 'Interaction',
    'Kanboard data' => 'Données Kanboard',
    'Scope' => 'Portée',

    // Settings: layout
    'Bar height (pixels)' => 'Hauteur des barres (pixels)',
    'Bar corner radius (pixels)' => 'Rayon des coins des barres (pixels)',
    'Dependency arrow curve radius' => 'Rayon de courbure des flèches de dépendance',
    'Padding around bars (pixels)' => 'Marge autour des barres (pixels)',
    'Column width (pixels, empty for the view mode default)' => 'Largeur des colonnes (pixels, vide pour la valeur par défaut du mode d\'affichage)',
    'Upper header height (pixels)' => 'Hauteur de l\'en-tête supérieur (pixels)',
    'Lower header height (pixels)' => 'Hauteur de l\'en-tête inférieur (pixels)',
    'Chart height ("auto" or a number of pixels)' => 'Hauteur du diagramme (« auto » ou un nombre de pixels)',
    'Grid lines' => 'Lignes de la grille',
    'Show the task column beside the chart' => 'Afficher la colonne des tâches à côté du diagramme',
    'Task column width (pixels)' => 'Largeur de la colonne des tâches (pixels)',
    'Extra fields in the task column' => 'Champs supplémentaires dans la colonne des tâches',
    'Both' => 'Les deux',
    'Vertical only' => 'Verticales uniquement',
    'Horizontal only' => 'Horizontales uniquement',

    // Settings: timeline
    'Default view mode' => 'Mode d\'affichage par défaut',
    'Selectable view modes' => 'Modes d\'affichage disponibles',
    'Show the view mode selector' => 'Afficher le sélecteur de mode d\'affichage',
    'Show the "Today" button' => 'Afficher le bouton « Aujourd\'hui »',
    'Extend the timeline while scrolling' => 'Étendre la frise lors du défilement',
    'Scroll to on load ("today", "start", "end" or a date)' => 'Position au chargement (« today », « start », « end » ou une date)',
    'Snap dragging to (for example "1d", empty for the view mode default)' => 'Aligner le déplacement sur (par exemple « 1d », vide pour la valeur par défaut du mode d\'affichage)',

    // Settings: weekends and holidays
    'Weekend days' => 'Jours de week-end',
    'Highlight weekends' => 'Mettre en évidence les week-ends',
    'Holidays (one "YYYY-MM-DD: Label" per line)' => 'Jours fériés (un « AAAA-MM-JJ : libellé » par ligne)',
    'Holiday colour (any CSS colour, empty for the default)' => 'Couleur des jours fériés (toute couleur CSS, vide pour la valeur par défaut)',
    'Exclude weekends from durations' => 'Exclure les week-ends du calcul des durées',
    'Excluded dates (one "YYYY-MM-DD" per line)' => 'Dates exclues (une « AAAA-MM-JJ » par ligne)',
    'Sunday' => 'Dimanche',
    'Monday' => 'Lundi',
    'Tuesday' => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday' => 'Jeudi',
    'Friday' => 'Vendredi',
    'Saturday' => 'Samedi',

    // Settings: interaction
    'Read-only chart' => 'Diagramme en lecture seule',
    'Do not allow changing dates' => 'Interdire la modification des dates',
    'Do not allow changing progress' => 'Interdire la modification de l\'avancement',
    'Keep the duration fixed while dragging' => 'Conserver la durée lors du déplacement',
    'Moving a task moves the tasks that depend on it' => 'Déplacer une tâche déplace les tâches qui en dépendent',
    'Keep bar labels visible while scrolling' => 'Garder les libellés des barres visibles lors du défilement',
    'Show the popup on' => 'Afficher la fenêtre contextuelle au',
    'Click' => 'Clic',
    'Hover' => 'Survol',
    'Highlight the column under the cursor' => 'Mettre en évidence la colonne sous le curseur',
    'Show expected progress' => 'Afficher l\'avancement attendu',

    // Settings: Kanboard data
    'Task order' => 'Ordre des tâches',
    'Board position' => 'Position sur le tableau',
    'Show subtasks as child rows' => 'Afficher les sous-tâches comme lignes enfants',
    'Include closed tasks' => 'Inclure les tâches fermées',
    'Link types drawn as dependencies' => 'Types de lien représentés comme dépendances',
    'Open the task on' => 'Ouvrir la tâche au',
    'Double click' => 'Double-clic',
    'Single click' => 'Clic simple',
    'Never' => 'Jamais',
    'Bar colour taken from' => 'Couleur des barres issue de',
    'Task colour' => 'Couleur de la tâche',
    'Category colour' => 'Couleur de la catégorie',
    'No colour' => 'Aucune couleur',

    // Settings: actions and per-project scope
    'Reset to defaults' => 'Réinitialiser aux valeurs par défaut',
    'Reset all Gantt settings to their default values?' => 'Réinitialiser tous les paramètres du Gantt à leurs valeurs par défaut ?',
    'Use settings specific to this project' => 'Utiliser des paramètres spécifiques à ce projet',
    'When this is unchecked the project follows the global Gantt settings and the values below are ignored.' => 'Lorsque cette case est décochée, le projet suit les paramètres globaux du Gantt et les valeurs ci-dessous sont ignorées.',
);
