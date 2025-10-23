<?php
/**
 * Fichier: services/class-template-parser.php
 * Service de parsing des templates d'emails
 * Remplace les variables {{variable}} par les valeurs réelles
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Template_Parser {
    
    /**
     * Parser un template et remplacer les variables
     * Variables supportées : {{post_title}}, {{post_url}}, {{post_excerpt}}, etc.
     */
    public static function parse($template, $variables) {
        
        if (empty($template)) {
            return '';
        }
        
        // Remplacer chaque variable par sa valeur
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        // Nettoyer les variables non remplacées
        $template = preg_replace('/\{\{[^}]+\}\}/', '', $template);
        
        return $template;
    }
    
    /**
     * Obtenir la liste des variables disponibles
     */
    public static function get_available_variables() {
        return array(
            '{{post_title}}' => 'Titre de l\'article',
            '{{post_url}}' => 'URL de l\'article',
            '{{post_excerpt}}' => 'Extrait de l\'article',
            '{{post_content}}' => 'Contenu complet de l\'article',
            '{{post_date}}' => 'Date de publication',
            '{{post_author}}' => 'Auteur de l\'article',
            '{{category_name}}' => 'Nom de la catégorie',
            '{{site_name}}' => 'Nom du site',
            '{{site_url}}' => 'URL du site',
            '{{lead_email}}' => 'Email du lead',
            '{{lead_first_name}}' => 'Prénom du lead',
            '{{lead_last_name}}' => 'Nom du lead',
        );
    }
}
