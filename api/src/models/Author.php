<?php

namespace API\Model;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;

class Author extends Model {
   protected $table = 'author';
   protected $visible = ['id', 'name', 'plugin_count'];
   public $timestamps = false;

   public function user() {
      return $this->hasOne('\API\Model\User');
   }

   public function plugins() {
      return $this->belongsToMany('\API\Model\Plugin', 'plugin_author');
   }

   public function scopeWithPluginCount($query) {
      $query->select(['author.id', 'author.name', DB::raw('COUNT(plugin_author.plugin_id) as plugin_count')])
            ->leftJoin('plugin_author', 'author.id', '=', 'plugin_author.author_id')
            ->groupBy('plugin_author.author_id');
      return $query;
   }

   public function scopeContributorsOnly($query) {
      $newQuery = \Illuminate\Database\Capsule\Manager::table(
                        \Illuminate\Database\Capsule\Manager::raw(
                           "({$query->toSql()}) as sub"))
                              ->mergeBindings($query->getQuery())
                              ->where('plugin_count', '!=', '0');
      return $newQuery;
   }

   public function scopeMostActive($query, $limit = false) {
      $query->select(['author.id', 'author.name', DB::raw('COUNT(plugin_author.plugin_id) as plugin_count')])
            ->leftJoin('plugin_author', 'author.id', '=', 'plugin_author.author_id')
            ->groupBy('author.name')
            ->orderBy('plugin_count', 'DESC');
      if ($limit != false) {
         $query->take($limit);
      }
      return $query;
   }

   /*
    * fixKnownDuplicates()
    *
    * This function is very specific,
    * it aims to provide a fix to current
    * state of things in xml files.
    *
    * Currently, some authors are duplicates,
    * and spelled differently depending on
    * plugins, this functions aims to ensure
    * correct detection of EACH author.
    *
    * This function shouldn't be here and might
    * dissapear someday.
    */
   private static $fkd_separators = [',', '/'];
   private static $fkd_duplicates = [
      [
         "names" => ['Xavier Caillaud / Infotel',
                     'Xavier CAILLAUD'],
         "ends"  => 'Xavier Caillaud'
      ],
      [
         "names" => ['Nelly LASSON',
                     'Nelly MAHU-LASSON'],
         "ends"  => 'Nelly Mahu-Lasson'
      ],
      [
         "names" => ['David DURIEUX'],
         "ends"  => 'David Durieux'
      ],
      [
         "names" => ['Olivier DURGEAU'],
         "ends"  => 'Olivier Durgeau'
      ],
      [
         "names" => ['Yohan BOUSSION'],
         "ends"  => 'Yohan Boussion'
      ],
      [
         "names" => ['Philippe GODOT'],
         "ends"  => 'Philippe Godot'
      ],
      [
         "names" => ['Cyril ZORMAN'],
         "ends"  => 'Cyril Zorman'
      ],
      [
         "names" => ['Maxime BONILLO'],
         "ends"  => 'Maxime Bonillo'
      ],
      [
         "names" => ['Philippe THOIREY'],
         "ends"  => 'Philippe Thoirey'
      ]
   ];
   public static function fixKnownDuplicates($author_string) {
      $detectedAuthors = [];
      // empty string
      if ($author_string == '') {
         return $detectedAuthors;
      }
      // detecting known duplicates
      foreach(self::$fkd_duplicates as $known_duplicate) {
         foreach ($known_duplicate['names'] as $known_name) {
               if (preg_match('/'.preg_quote($known_name, '/').'/', $author_string)) {
                  $author_string = preg_replace('/'.preg_quote($known_name, '/').'/',
                                         $known_duplicate['ends'],
                                         $author_string);
               }
         }
      }

      // detecting inline multiple authors
      foreach(self::$fkd_separators as $separator) {
         $found_authors = explode($separator, $author_string);
         if (sizeof($found_authors) > 1) {
            foreach ($found_authors as $author) {
               $detectedAuthors[] = trim($author);
            }
            break;
         }
      }

      if (sizeof($detectedAuthors) == 0) {
         return [trim($author_string)];
      } else {
         return $detectedAuthors;
      }
   }
}