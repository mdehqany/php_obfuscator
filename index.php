<?php
/*
First Of all Remove All Comments From Your PHP File and THen obfuscate it
*/
$code = "";
function codein_hex_encode($str){
    $str = str_split($str);
    $out = "";
    foreach($str as $st){
        $out.="\x".dechex(ord($st));
    }
    return $out;
}
function get_char($i){
    global $code;
    return substr($code,$i,1);
}
function encode($incode){
    global $code;
//Lets Set Default Values
    $code = $incode;
    $i = 0;
    $res = "";
    $php = -1;
    $str = -1;
    $len = strlen($code);
    $html = -1;
    $script = -1;
    $o_funcs = array();
    while($i<$len){
        //Its Read File Charecter By Charecter
        $char = substr($code,$i,1);
        if($char == "<"){
            //Check If PHP Codes Start
            if(($char.get_char($i+1).get_char($i+2).get_char($i+3).get_char($i+4) == "<?php") || ($char.get_char($i+1).get_char($i+2) == "<?=")){
                $php = $i+5;
                if($html > 0){
                    //Ok if We Have HTML Code , Lets Encrypt It and Put It In Main String
                    $hex = '<?php echo "'.codein_hex_encode(substr($code,$html,$i-$html)).'"; ?>';
                    $code = substr($code,0,$html).$hex.substr($code,$i,$len);
                    $html = -1;
                    $i+=strlen($hex)-(2+(substr_count($hex,"\\")));
                    $len = strlen($code);
                }
            }
        }
        //If Detect Variable In Any String
        if(get_char($i-1) != '\\' && get_char($i+1) != '"' && $char == "$" && get_char($str) == '"' && $php > 0 && $str > 0 && $script <= 0){
            $cnt = $i+1;
            //Get Charecters While its in variable name
            while(preg_match("#[a-zA-Z0-9\-_>]#",get_char($cnt))){
                $cnt+=1;
            }
            $text = '".'.substr($code,$i,$cnt-$i).(get_char($cnt) != '"' ? '."' : "");
            $code = substr($code,0,$i).$text.substr($code,$cnt+(get_char($cnt) == '"' ? 1 : 0),$len);
            $i-=6;
            $len = strlen($code);
        }
        //Detect If Its JavaScript Tag
        if($char == "<"){
            if($char.get_char($i+1).get_char($i+2).get_char($i+3).get_char($i+4).get_char($i+5).get_char($i+6).get_char($i+7) == "<script>" ){
                $script = $i;
            }
            if($char.get_char($i+1).get_char($i+2).get_char($i+3).get_char($i+4).get_char($i+5).get_char($i+6).get_char($i+7).get_char($i+8) == "<\/script>"){
                $script = -1;
            }
        }
        //If Its PHP String Let Encrypt It
        if(($char == "'" || $char == '"') && $php > 0){
            if($str == -1){
                $str = $i;
            } else if($str > 0 && get_char($str) == $char && $i-$str > 1 && !(get_char($i-1) == "\\" && get_char($i-2) != "\\")){
                $str +=1;
                $text = substr($code,$str,$i-$str);
                if(substr($text,0,2) == "\\\\") { $text = substr($text,1); }
                $hex = '"'.codein_hex_encode($text).'"';
                $code = substr($code,0,$str-1).$hex.substr($code,$i+1,$len);
                $str = -1;
                $i+=strlen($hex)-(2+(substr_count($hex,"\\")));
                $len = strlen($code);
            } else if (get_char($str) == $char && $i-$str <= 1){
                $str = -1;
            }
        }
        //Detect If PHP Ends
        if($char == "?"){
            if(get_char($i+1) == ">"){
                $php = -1;
                $html = $i+2;
            }
        }
        $i+=1;
    }
    //Lets Encrypt All Functions In Our Code String
    preg_match_all("#([A-Za-z_]+)\(#mU",$code,$exfuncs);
    $funcs = $exfuncs[1];
    $not = array("function");
    $befs = array(" ",".","(");
    foreach($funcs as $func){
        //Check If Its Pure PHP Function and Not In Our $not array
        if(!in_array($func,$not) && function_exists($func)){
            //Check If Does Not Encoded Before
            if(!array_key_exists($func,$o_funcs)){
                $mdfunc = md5($func);
                $cnt = 0;
                //If Its Start With Number We Shoud Remove It
                while(preg_match("#[0-9]#",substr($mdfunc,$cnt,1)) ){ $cnt+=1; }
                $mdfunc = substr($mdfunc,$cnt);
                $strenc = '$GLOBALS["'.$mdfunc.'"] = "'.codein_hex_encode($func).'";';
                $code = substr($code,0,5).$strenc.substr($code,5);
                $o_funcs[$func] = $mdfunc;
            }
            $pos = 0;
            //Ok Lets Convert All Function Names In Our Code
            while($pos < $len){
                $text = strpos($code,$func,$pos);
                //Ok If That Fuction is After Define it ( I think we dont need this )
                $posfunc = strpos($code,"function ".$func,$pos)+strlen($func);
                if($text && ($posfunc < $text || $posfunc < 0 ) ){
                    $bef = substr($code,$text-1,1);
                    $aft = substr($code,$text+strlen($func),1);
                    if(in_array($bef,$befs) && $aft == "("){
                        $encfunc = '$GLOBALS["'.$o_funcs[$func].'"]';
                        $code = substr($code,0,$text).$encfunc.substr($code,$text+strlen($func));
                    }
                }
                $pos+=$text+strlen($func)+1;
            }
        }
    }


    $code = preg_replace("#\/\/(.*)\n#U","",$code);
    if(isset($_POST['line_break'])){
    $code = preg_replace( "/\r|\n/","",$code);
    }
    $code = preg_replace("#<\?php#","<?php ",$code);
    return $code;
}
if(isset($_POST['encode'])){
    $file = $_POST['file'];
    if(file_exists($file) && !empty($file)){
        $encoded = encode(file_get_contents($file));
        file_put_contents("encodes/".basename($file),$encoded);
    }
}

?>
<html>
<head>
    <style>
        .tbl {
            width:400px;
            height:auto;
            margin:20px auto;
            padding:10px;
            background:#efefef;
            border:1px solid #ccc;
            text-align:center;
        }
    </style>
</head>
<body>
<div class="tbl">
    <form method="post">
        <select name="file">
        <?php foreach(glob("codes/*.php") as $file){ echo '<option>'.$file.'</option>'; } ?>
        </select><br>
        Line Break : <input type="checkbox" name="line_break" checked><br>
        <input type="submit" value="Encode" name="encode">
    </form>
</div>
</body>
</html>