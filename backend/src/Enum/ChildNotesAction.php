<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Actions distinctes du domaine children_notes (Lot 2 §3.3) : séparent l'édition de **contenu**
 * (notes) de l'édition de **structure** (profil enfant) — un `child` ne gère jamais la structure.
 */
enum ChildNotesAction
{
    case ChildView;    // voir un profil enfant
    case ChildManage;  // créer/éditer/supprimer un profil (structure)
    case NoteView;     // lister/lire les notes
    case NoteEdit;     // ajouter/éditer/supprimer une note (contenu)
}
