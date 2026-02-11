<?php
// If this file is accessed directly (not via the app bootstrap),
// allow it only for authenticated sessions. Previously this would
// terminate with a plain message; now we redirect unauthenticated
// visitors to the app login so MVC session handling is used.
if (!defined('PATHSPAGE')) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
        header('Location: /login');
        exit;
    }
    if (!defined('PATHSPAGE')) define('PATHSPAGE', true);
}

// No direct DB includes here; the controller provides the application's DB
// instance (via `$db`) and views must not attempt to include DB libraries.

// Backwards-compatibility adapter: legacy views expect a MysqliDb-like
// API (`rawQueryOne`, `rawQuery`, `count`, `quote`, etc.). Wrap the
// application's DB instance when it doesn't already provide those.
class LegacyDbAdapter {
    private $inner;
    private $lastCount = 0;

    public function __construct($inner = null) {
        $this->inner = $inner;
    }

    public function query($sql, $params = null) {
        try {
            if ($this->inner) {
                // If inner exposes prepare, use it for parameterized queries
                if (!is_null($params) && method_exists($this->inner, 'prepare')) {
                    $stmt = $this->inner->prepare($sql);
                    $stmt->execute($params);
                    $this->lastCount = $stmt->rowCount();
                    return $stmt;
                }

                // If inner is a PDO or exposes query(), delegate
                if (method_exists($this->inner, 'query')) {
                    $stmt = $this->inner->query($sql);
                    $this->lastCount = $stmt ? $stmt->rowCount() : 0;
                    return $stmt;
                }
            }

            // Fallback: if inner is a PDO instance
            if ($this->inner instanceof PDO) {
                if (is_null($params)) {
                    $stmt = $this->inner->query($sql);
                    $this->lastCount = $stmt ? $stmt->rowCount() : 0;
                    return $stmt;
                }
                $stmt = $this->inner->prepare($sql);
                $stmt->execute($params);
                $this->lastCount = $stmt->rowCount();
                return $stmt;
            }
        } catch (\Throwable $_) {
            // don't break legacy views on adapter errors
        }
        return false;
    }

    public function rawQuery($sql) {
        return $this->query($sql);
    }

    public function rawQueryOne($sql) {
        $stmt = $this->query($sql);
        if ($stmt) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
        return null;
    }

    public function prepare($sql) {
        if ($this->inner && method_exists($this->inner, 'prepare')) {
            return $this->inner->prepare($sql);
        }
        if ($this->inner instanceof PDO) {
            return $this->inner->prepare($sql);
        }
        throw new \RuntimeException('No underlying DB available for prepare()');
    }

    public function get($table, $cols = '*', $where = null) {
        $colsStr = is_array($cols) ? implode(',', $cols) : $cols;
        $sql = "SELECT $colsStr FROM $table";
        $params = null;
        if ($where) {
            if (is_array($where)) {
                $parts = [];
                $params = [];
                foreach ($where as $k => $v) {
                    $parts[] = "$k = :$k";
                    $params[":$k"] = $v;
                }
                $sql .= ' WHERE ' . implode(' AND ', $parts);
            } else {
                $sql .= ' WHERE ' . $where;
            }
        }
        $stmt = $this->query($sql, $params);
        if ($stmt) return $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [];
    }

    public function quote($value) {
        if ($this->inner && method_exists($this->inner, 'quote')) {
            return $this->inner->quote($value);
        }
        if ($this->inner instanceof PDO) {
            return $this->inner->quote($value);
        }
        return "'" . addslashes($value) . "'";
    }

    public function __get($name) {
        if ($name === 'count') return $this->lastCount;
        return null;
    }
}

// If a controller provided `$db` but it lacks legacy methods, wrap it.
if (isset($db) && !method_exists($db, 'rawQueryOne')) {
    $db = new LegacyDbAdapter($db);
    $GLOBALS['db'] = $db;
}

ini_set('display_errors', 1);
// Avoid using deprecated E_STRICT constant; use E_ALL instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
error_reporting(0);

$whitelist = array(
    '127.0.0.1',
    '::1',
    '192.168.43.1',
    '192.168.43.84'
);
// Database credentials should come from the application's configuration (.env)
// or be provided by the controller. This view must not define DB constants
// or contain environment-specific credentials.

/* 
    FGBible Alorithm version 1
    This module can later be integrated into the init script on page load
    to maximize performance
*/


$Book["All"][1]="Genesis";
$BookIndex["Genesis"]="1";
$Book["Old Testament"][1]="Genesis";
$Book["All"][2]="Exodus";
$BookIndex["Exodus"]="2";
$Book["Old Testament"][2]="Exodus";
$Book["All"][3]="Leviticus";
$BookIndex["Leviticus"]="3";
$Book["Old Testament"][3]="Leviticus";
$Book["All"][4]="Numbers";
$BookIndex["Numbers"]="4";
$Book["Old Testament"][4]="Numbers";
$Book["All"][5]="Deuteronomy";
$BookIndex["Deuteronomy"]="5";
$Book["Old Testament"][5]="Deuteronomy";
$Book["All"][6]="Joshua";
$BookIndex["Joshua"]="6";
$Book["Old Testament"][6]="Joshua";
$Book["Historical Books"][6]="Joshua";
$Book["All"][7]="Judges";
$BookIndex["Judges"]="7";
$Book["Old Testament"][7]="Judges";
$Book["Historical Books"][7]="Judges";
$Book["All"][8]="Ruth";
$BookIndex["Ruth"]="8";
$Book["Old Testament"][8]="Ruth";
$Book["Historical Books"][8]="Ruth";
$Book["All"][9]="1Samuel";
$BookIndex["1Samuel"]="9";
$Book["Old Testament"][9]="1Samuel";
$Book["Historical Books"][9]="1Samuel";
$Book["All"][10]="2Samuel";
$BookIndex["2Samuel"]="10";
$Book["Old Testament"][10]="2Samuel";
$Book["Historical Books"][10]="2Samuel";
$Book["All"][11]="1Kings";
$BookIndex["1Kings"]="11";
$Book["Old Testament"][11]="1Kings";
$Book["Historical Books"][11]="1Kings";
$Book["All"][12]="2Kings";
$BookIndex["2Kings"]="12";
$Book["Old Testament"][12]="2Kings";
$Book["Historical Books"][12]="2Kings";
$Book["All"][13]="1Chronicles";
$BookIndex["1Chronicles"]="13";
$Book["Old Testament"][13]="1Chronicles";
$Book["Historical Books"][13]="1Chronicles";
$Book["All"][14]="2Chronicles";
$BookIndex["2Chronicles"]="14";
$Book["Old Testament"][14]="2Chronicles";
$Book["Historical Books"][14]="2Chronicles";
$Book["All"][15]="Ezra";
$BookIndex["Ezra"]="15";
$Book["Old Testament"][15]="Ezra";
$Book["Historical Books"][15]="Ezra";
$Book["All"][16]="Nehemiah";
$BookIndex["Nehemiah"]="16";
$Book["Old Testament"][16]="Nehemiah";
$Book["Historical Books"][16]="Nehemiah";
$Book["All"][17]="Esther";
$BookIndex["Esther"]="17";
$Book["Old Testament"][17]="Esther";
$Book["Historical Books"][17]="Esther";
$Book["All"][18]="Job";
$BookIndex["Job"]="18";
$Book["Wisdom Books"][18]="Job";
$Book["Old Testament"][18]="Job";
$Book["All"][19]="Psalms";
$BookIndex["Psalms"]="19";
$Book["Wisdom Books"][19]="Psalms";
$Book["Old Testament"][19]="Psalms";
$Book["All"][20]="Proverbs";
$BookIndex["Proverbs"]="20";
$Book["Wisdom Books"][20]="Proverbs";
$Book["Old Testament"][20]="Proverbs";
$Book["All"][21]="Ecclesiastes";
$BookIndex["Ecclesiastes"]="21";
$Book["Wisdom Books"][21]="Ecclesiastes";
$Book["Old Testament"][21]="Ecclesiastes";
$Book["All"][22]="Song of Solomon";
$BookIndex["Song of Solomon"]="22";
$Book["Wisdom Books"][22]="Song of Solomon";
$Book["Old Testament"][22]="Song of Solomon";
$Book["All"][23]="Isaiah";
$BookIndex["Isaiah"]="23";
$Book["Old Testament"][23]="Isaiah";
$Book["Major Prophets"][23]="Isaiah";
$Book["All"][24]="Jeremiah";
$BookIndex["Jeremiah"]="24";
$Book["Old Testament"][24]="Jeremiah";
$Book["Major Prophets"][24]="Jeremiah";
$Book["All"][25]="Lamentations";
$BookIndex["Lamentations"]="25";
$Book["Old Testament"][25]="Lamentations";
$Book["Major Prophets"][25]="Lamentations";
$Book["All"][26]="Ezekiel";
$BookIndex["Ezekiel"]="26";
$Book["Old Testament"][26]="Ezekiel";
$Book["Major Prophets"][26]="Ezekiel";
$Book["All"][27]="Daniel";
$BookIndex["Daniel"]="27";
$Book["Old Testament"][27]="Daniel";
$Book["Major Prophets"][27]="Daniel";
$Book["Apocalyptic Books"][27]="Daniel";
$Book["All"][28]="Hosea";
$BookIndex["Hosea"]="28";
$Book["Old Testament"][28]="Hosea";
$Book["Minor Prophets"][28]="Hosea";
$Book["All"][29]="Joel";
$BookIndex["Joel"]="29";
$Book["Old Testament"][29]="Joel";
$Book["Minor Prophets"][29]="Joel";
$Book["All"][30]="Amos";
$BookIndex["Amos"]="30";
$Book["Old Testament"][30]="Amos";
$Book["Minor Prophets"][30]="Amos";
$Book["All"][31]="Obadiah";
$BookIndex["Obadiah"]="31";
$Book["Old Testament"][31]="Obadiah";
$Book["Minor Prophets"][31]="Obadiah";
$Book["All"][32]="Jonah";
$BookIndex["Jonah"]="32";
$Book["Old Testament"][32]="Jonah";
$Book["Minor Prophets"][32]="Jonah";
$Book["All"][33]="Micah";
$BookIndex["Micah"]="33";
$Book["Old Testament"][33]="Micah";
$Book["Minor Prophets"][33]="Micah";
$Book["All"][34]="Nahum";
$BookIndex["Nahum"]="34";
$Book["Old Testament"][34]="Nahum";
$Book["Minor Prophets"][34]="Nahum";
$Book["All"][35]="Habakkuk";
$BookIndex["Habakkuk"]="35";
$Book["Old Testament"][35]="Habakkuk";
$Book["Minor Prophets"][35]="Habakkuk";
$Book["All"][36]="Zephaniah";
$BookIndex["Zephaniah"]="36";
$Book["Old Testament"][36]="Zephaniah";
$Book["Minor Prophets"][36]="Zephaniah";
$Book["All"][37]="Haggai";
$BookIndex["Haggai"]="37";
$Book["Old Testament"][37]="Haggai";
$Book["Minor Prophets"][37]="Haggai";
$Book["All"][38]="Zechariah";
$BookIndex["Zechariah"]="38";
$Book["Old Testament"][38]="Zechariah";
$Book["Minor Prophets"][38]="Zechariah";
$Book["All"][39]="Malachi";
$BookIndex["Malachi"]="39";
$Book["Old Testament"][39]="Malachi";
$Book["Minor Prophets"][39]="Malachi";
$Book["All"][40]="Matthew";
$BookIndex["Matthew"]="40";
$Book["New Testament"][40]="Matthew";
$Book["Gospels"][40]="Matthew";
$Book["All"][41]="Mark";
$BookIndex["Mark"]="41";
$Book["New Testament"][41]="Mark";
$Book["Gospels"][41]="Mark";
$Book["All"][42]="Luke";
$BookIndex["Luke"]="42";
$Book["New Testament"][42]="Luke";
$Book["Gospels"][42]="Luke";
$Book["All"][43]="John";
$BookIndex["John"]="43";
$Book["New Testament"][43]="John";
$Book["Gospels"][43]="John";
$Book["All"][44]="Acts";
$BookIndex["Acts"]="44";
$Book["New Testament"][44]="Acts";
$Book["All"][45]="Romans";
$BookIndex["Romans"]="45";
$Book["New Testament"][45]="Romans";
$Book["Pauline Epistles"][45]="Romans";
$Book["All"][46]="1Corinthians";
$BookIndex["1Corinthians"]="46";
$Book["New Testament"][46]="1Corinthians";
$Book["Pauline Epistles"][46]="1Corinthians";
$Book["All"][47]="2Corinthians";
$BookIndex["2Corinthians"]="47";
$Book["New Testament"][47]="2Corinthians";
$Book["Pauline Epistles"][47]="2Corinthians";
$Book["All"][48]="Galatians";
$BookIndex["Galatians"]="48";
$Book["New Testament"][48]="Galatians";
$Book["Pauline Epistles"][48]="Galatians";
$Book["All"][49]="Ephesians";
$BookIndex["Ephesians"]="49";
$Book["New Testament"][49]="Ephesians";
$Book["Pauline Epistles"][49]="Ephesians";
$Book["All"][50]="Philippians";
$BookIndex["Philippians"]="50";
$Book["New Testament"][50]="Philippians";
$Book["Pauline Epistles"][50]="Philippians";
$Book["All"][51]="Colossians";
$BookIndex["Colossians"]="51";
$Book["New Testament"][51]="Colossians";
$Book["Pauline Epistles"][51]="Colossians";
$Book["All"][52]="1Thessalonians";
$BookIndex["1Thessalonians"]="52";
$Book["New Testament"][52]="1Thessalonians";
$Book["Pauline Epistles"][52]="1Thessalonians";
$Book["All"][53]="2Thessalonians";
$BookIndex["2Thessalonians"]="53";
$Book["New Testament"][53]="2Thessalonians";
$Book["Pauline Epistles"][53]="2Thessalonians";
$Book["All"][54]="1Timothy";
$BookIndex["1Timothy"]="54";
$Book["New Testament"][54]="1Timothy";
$Book["Pauline Epistles"][54]="1Timothy";
$Book["All"][55]="2Timothy";
$BookIndex["2Timothy"]="55";
$Book["New Testament"][55]="2Timothy";
$Book["Pauline Epistles"][55]="2Timothy";
$Book["All"][56]="Titus";
$BookIndex["Titus"]="56";
$Book["New Testament"][56]="Titus";
$Book["Pauline Epistles"][56]="Titus";
$Book["All"][57]="Philemon";
$BookIndex["Philemon"]="57";
$Book["New Testament"][57]="Philemon";
$Book["Pauline Epistles"][57]="Philemon";
$Book["All"][58]="Hebrews";
$BookIndex["Hebrews"]="58";
$Book["New Testament"][58]="Hebrews";
$Book["Pauline Epistles"][58]="Hebrews";
$Book["All"][59]="James";
$BookIndex["James"]="59";
$Book["New Testament"][59]="James";
$Book["Epistles"][59]="James";
$Book["All"][60]="1Peter";
$BookIndex["1Peter"]="60";
$Book["New Testament"][60]="1Peter";
$Book["Epistles"][60]="1Peter";
$Book["All"][61]="2Peter";
$BookIndex["2Peter"]="61";
$Book["New Testament"][61]="2Peter";
$Book["Epistles"][61]="2Peter";
$Book["All"][62]="1John";
$BookIndex["1John"]="62";
$Book["New Testament"][62]="1John";
$Book["Epistles"][62]="1John";
$Book["All"][63]="2John";
$BookIndex["2John"]="63";
$Book["New Testament"][63]="2John";
$Book["Epistles"][63]="2John";
$Book["All"][64]="3John";
$BookIndex["3John"]="64";
$Book["New Testament"][64]="3John";
$Book["Epistles"][64]="3John";
$Book["All"][65]="Jude";
$BookIndex["Jude"]="65";
$Book["New Testament"][65]="Jude";
$Book["Epistles"][65]="Jude";
$Book["All"][66]="Revelation";
$BookIndex["Revelation"]="66";
$Book["New Testament"][66]="Revelation";
$Book["Apocalyptic Books"][66]="Revelation";



function books($books, $term) {
    foreach ($books as $key => $name) {

        if (strpos($name, $term) !== false) {
            
            return array($key, $name);
        } else {
            
        }
    }
}


// Use the application's $db (PDO/Database wrapper) when available.
// If a controller provided `$db`, prefer that. Otherwise, gracefully no-op DB operations.
// Verse search endpoint (AJAX) — respond using $db if present.
if (!empty($_GET['verse'])) {
    $fetched = [];
    if (isset($db)) {
        try {
            $sql = "SELECT * FROM fgbibledb_kjv WHERE TEXT LIKE :t LIMIT 100";
            $rows = $db->query($sql, [':t' => '%' . $_GET['verse'] . '%'])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $pref) {
                $fetched[] = [
                    'verse' => '[' . ($Book['All'][$pref['BOOK']] ?? $pref['BOOK']) . ' ' . $pref['CHAPTER'] . ':' . $pref['VERSE'] . ']: ' . $pref['TEXT'],
                    'passage' => $pref['CHAPTER'] . ':' . $pref['VERSE'] . ']: ' . $pref['TEXT']
                ];
            }
        } catch (\Throwable $_) {
            // DB not available or query failed — return empty result
            $fetched = [];
        }
    }
    echo json_encode($fetched);
    exit;
}

// Database helper: use app DB if available, otherwise provide safe fallbacks
function escape_data($data) {
    if (isset($GLOBALS['db']) && method_exists($GLOBALS['db'], 'quote')) {
        // PDO::quote includes surrounding quotes, strip them
        $q = $GLOBALS['db']->quote($data);
        return substr($q, 1, -1);
    }
    if (ini_get('magic_quotes_gpc')) {
        $data = stripslashes($data);
    }
    return addslashes($data);
}

// split citation to array
$verses = '';


function show_r($string){
    // echo "<pre>".print_r($string,1)."</pre>";
}

function trim_citation($holy_str){
    // global $verses;

    //$holy_str = str_replace(';', '', $holy_str);

    $check_book_num = explode(" ", $holy_str); // Arange by ranges
    
    // 1 Cor 5:1
    if(is_numeric($check_book_num[0])){
        
        // remove the space in the numeric book name
        $holy_str = substr_replace($holy_str, '', 1, 1);
    }
    
    $citation = explode(";", $holy_str); // Arange by ranges
    
    //echo "<pre>".print_r($citation, 1)."</pre>";
    
    // check array size
    if(count($citation)){
        
        $book = $citation[0];   
        $getbook = explode(" ", $book); // extract book name
        
        //echo $getbook[0] . "<br />";
        
        // recreate the the array:
        $passage = array();

        foreach ($citation as $k => $v){

            //echo strpos($v, $getbook[0])===false;

            //echo "$v and $getbook[0]" . "<br />";
            
            if (strpos($v, $getbook[0]) === false){ // non-matching book name
                
                // remove the dot and insert "book name" to non-matched string
                $trim =  str_replace(".", " ", substr_replace($v, $getbook[0], 0, 0));
                $trim = preg_replace('/\s+/', ' ', $trim);
                $passage[] = $trim;

                // echo $trim; // Mat
                
            } elseif(strpos($v, $getbook[0]) !== false){ // remove the dot of matching book name
                //$verses = $v; 
                //echo $verses.' <br />';

                // echo "$v and $getbook[0]<br />";
                // $verses = $v;

                // $trim = str_replace(".", "", $v).preg_replace('/\s+/', ' ', $verses); // orig
                $trim = str_replace(".", "", $v).preg_replace('/\s+/', ' ', '');

                //echo "remove the dot of matching book name:  $trim <br />";
                $passage[] = preg_replace('/\s+/', ' ', $trim);

                //echo show_r($passage);
            }
        }
        
    }

    // echo "<pre>".print_r($citation, 1)."</pre>";
    // echo "<pre>".print_r($passage, 1)."</pre>";

    //echo show_r($passage);
    
    return $passage;
}


function getVerse($book, $chapter, $start, $end){
    
    // echo "getVerse($book, $chapter, [$start], $end)<br />";

    global $verse_result, $bible, $dbc;
    if(books($bible, $book)) {
        $r = books($bible, $book);
        $_GET['book'] = $r[0];
        $book_name = $r[0];
        
        if(!preg_match("/[to]/i", $start)){ // check for "to"
        
            if(isset($end)){ // query has start and end
            
                // Mat. 2:1-5;
                // Format: b c : v-v
                
                 // echo "case 1 > complete verse <br />";
                
                $query = "
                SELECT * FROM fgbibledb_kjv 
                WHERE BOOK=$book_name AND 
                CHAPTER=$chapter AND
                VERSE>=$start AND VERSE<=$end";
            } elseif(isset($start)) { // query has a verse
            
                # echo "case 2 chapter and verse";
                $parts = explode(",", $start);
                
                    // Mat 6:7,9
                    // Format: b c : v, v
                
                if(count($parts)>=2){   
                    
                    $vcount = 0;
                    
                    $query = "
                    (SELECT * FROM fgbibledb_kjv 
                    WHERE BOOK=$book_name AND 
                    CHAPTER=$chapter AND
                    VERSE={$parts[0]}) ";
                    foreach($parts as $vv){
                        $vcount++;
                        $query .= "UNION 
                        (SELECT * FROM fgbibledb_kjv 
                        WHERE BOOK=$book_name AND 
                        CHAPTER=$chapter AND
                        VERSE=$vv)";
                    }
    
                    $query .= ";";
                } else {
                    $query = "
                    SELECT * FROM fgbibledb_kjv 
                    WHERE BOOK=$book_name AND 
                    CHAPTER=$chapter AND
                    VERSE={$parts[0]}";
                }
            } elseif(isset($chapter) && !isset($start)) {
                
                # echo "case 3 > chapter only";
                $query = "
                SELECT * FROM fgbibledb_kjv 
                WHERE BOOK=$book_name AND 
                CHAPTER=$chapter;";
                
            } else {
                //echo "toa";
            }
            
        } else {
            $range = explode("to", $start);
            
            // add 1 to the chapter and iterate:
            for($c = $chapter; $c<=$range[1]; $c++){
                
                // call up to chapter only
                //echo "<br /> $c<br />";
                if($c==$chapter){
                    $query = "
                    (SELECT * FROM fgbibledb_kjv 
                    WHERE BOOK=$book_name AND 
                    CHAPTER=$chapter AND
                    VERSE>={$range[0]}) ";
                } else {
                    $query .= "UNION
                    (SELECT * FROM fgbibledb_kjv 
                    WHERE BOOK=$book_name AND 
                    CHAPTER=$c ) ";
                }
                
                if($c==$range[1]){
                    $query .= "UNION
                    (SELECT * FROM fgbibledb_kjv 
                    WHERE BOOK=$book_name AND 
                    CHAPTER=$range[1] AND
                    VERSE<=$end) ;";
                    
                }
            }
        }
        //$mysqli = mysqli_connect("localhost", "root", "@mysq|inx!2021_A", "ugnayan_fgc");

        //echo $query . "<br />";
        // Execute query using app DB if available
        if (isset($GLOBALS['db'])) {
            try {
                $stmt = $GLOBALS['db']->query($query);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $book = $row['BOOK'];
                    $chapter = $row['CHAPTER'];
                    $verse = $row['VERSE'];
                    $verse_text = $row['TEXT'];
                    if ($verse == 1) $verse = '';
                    $verse_result .= "<span class='verse' id='$verse'>$verse</span> $verse_text \n<br />";
                }
            } catch (\Throwable $_) {
                // query failed — leave $verse_result unchanged
            }
        }
        
        $search_result = "$book_name $chapter:$verse";
        $bible_verse = "$book_name $chapter:$verse";


        //echo $verse_result . "<br />";
        
        
    } else {
        //echo "no book!<br />";
    }

    //echo $verse_result;
    return $verse_result;
}

$detectrange = false;

function checkRange($rangefilter){
    
    $regex = "/\b[a-zA-Z]+(?:\s+\d+)?(?::\d+(?:–\d+)?(?:[,;]\s*\d+(?:–\d+)?)*)?/";
    
    //echo "$regex<br /><br />";
    //echo preg_match($regex, $holy_str);
    return preg_match($regex, $rangefilter);
}



?>