<?php 
if (!isset($initok)) {echo "do not run this script directly";exit;}

if (!isset($_POST['nextstep']))
	$nextstep=0;
else
	$nextstep=$_POST['nextstep'];


if (!isset($_POST['imfn']))
	$imfn="";
else
	$imfn=$_POST['imfn'];


/* csv field number to name mapping */
$fno2name=array(
/*0*/    'label',
/*1*/    'location',
/*2*/    'area',
/*3*/    'username',
/*4*/    'status',
/*5*/    'dnsname',
/*6*/    'ipv4',
/*7*/    'comments',
/*8*/    'manufacturer',
/*9*/    'model',
/*10*/   'sn',
/*11*/   'itemtype',
/*12*/   'function',
/*13*/   'cpu',
/*14*/   'ram',
/*15*/   'hd',
/*16*/   'cpuno',
/*17*/	 'remadmip',
/*18 - lookup*/ 'rack',
/*19*/	 'rackposition',
/*20*/	 'usize',

/*21*/          'umdecal',
/*22*/          'admlogin',
/*23*/          'admloginsc',
/*24*/          'pubip',
/*25*/          'hdtypes',
/*26*/          'owner',
/*27*/          'extadm',

);

$name2fno=array_flip($fno2name);

$nfields=count($fno2name);


//nextstep:
//0: show import form
//1: import file and if not successfull go to 0 else show imported file and fields, and candidate db objects
//2: DB insert

//echo "<p>NEXT1=$nextstep<br>";

if ($nextstep==1 && strlen($_FILES['file']['name'])>2) { //insert file
  $filefn=strtolower("import-".$_COOKIE["itdbuser"]."-".validfn($_FILES['file']['name']));
  $uploadedfile = "/tmp/".$filefn;
  $result = '';

  //Move the file from the stored location to the new location
  if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadedfile)) {
	  $result = "Cannot upload the file '".$_FILES['file']['name']."'"; 
	  if(!file_exists($uploaddir)) {
		  $result .= " : Folder doesn't exist.";
	  } elseif(!is_writable($uploaddir)) {
		  $result .= " : Folder not writable.";
	  } elseif(!is_writable($uploadedfile)) {
		  $result .= " : File not writable.";
	  }
	  $filefn = '';

	  echo "<br><b>ERROR: $result</b><br>";
	  $imfn="";
	  $nextstep=0;
  }
  else { //file ok
	  $nextstep=1;
	  //print "<br>Uploaded  $uploadedfile<br>";
	  $imfn=$uploadedfile;
	}
}//insert file
?>

<div style='width:100%;'> <!-- import1 -->

<?php if ($nextstep==0) { ?>
<h1>Experimental import </h1>
<h2>BACKUP your ITDB FIRST!</h2>
<table>
<form method=post name='importfrm' action='<?php echo $scriptname?>?action=<?php echo $action?>' enctype='multipart/form-data'>
<tr>
<tr><td>File:</td><td> <input name="file" id="file" size="25" type="file"></td></tr>
<tr><td>Delimeter:</td><td> <input size=1 type=text name='delim' value=';' maxlength=1></td></tr>
<tr><td>Skip 1st row:</td><td><select name=skip1st><option value=1>Yes</option><option value=0>No</option></select></td></tr>
<tr><td colspan=2><input type=submit value='Upload file and inspect fields'></td></tr>
<input type=hidden name='nextstep' value='1'>
<input type=hidden name='imfn' value='<?php echo $imfn?>'>
</form>
<p>
Expected format is CSV file with the following fields:<br>
<big>
<p>
    <?php
    $sep="";
    foreach ($fno2name as $name) {
        echo $sep.ucfirst($name);
        $sep=", ";
    }
    ?>
    </big>
    </p>
<br>
<?php }?>

<?php if ($nextstep==1) { 
	$delim=$_POST['delim'];
	$imlines=file($imfn);
?>

	<br><h2> Please check fields for consistency before submitting.</h2>
    <h2>Existing racks will import with DB information.  New racks will import with generic stats.</h2>
	<div style='height:400px;overflow:auto'>
	<table class='brdr sortable'>
	<thead>
    <tr>
    <?php
    foreach ($fno2name as $name)
        echo "<th>$name</th>\n";
    ?>
    </tr>
	</thead>
	<tbody>

	<?php
	foreach ($imlines as $line_num => $line) {
		if ($line_num==0 && $_POST['skip1st']) 
			continue;

		$cols=explode($delim,$line);
		if (count($cols) != $nfields) {
			echo "<b><big>Error: field count in line $line_num is ".count($cols).", $nfields is expected</big></b>";
			$nextstep=0;
			break;
		}
		echo "<tr>";
		foreach ($cols as $col) {
			$col=trim($col);
			echo "<td>$col</td>";
		}
		echo "</tr>\n";
		//echo "Line #<b>{$line_num}</b> : " . htmlspecialchars($line) . "<br />\n";

		//hw manufacturer
		if (gethwmanufacturerbyname($cols[$name2fno['manufacturer']])>=0) 
			$hwman_old[]=trim($cols[$name2fno['manufacturer']]);
		else 
			$hwman_new[]=trim($cols[$name2fno['manufacturer']]);

        //echo "HERE:\n"; print_r($hwman_new); echo "col:<br>\n"; print_r($cols); echo "<br>";

		//users
		if (getuserbyname($cols[$name2fno['username']])>=0) 
			$user_old[]=trim($cols[$name2fno['username']]);
		elseif (strlen(trim($cols[$name2fno['username']])))
			$user_new[]=trim($cols[$name2fno['username']]);

		//itemtypes
		if (getitemtypeidbyname($cols[$name2fno['itemtype']])>=0) 
			$itypes_old[]=trim($cols[$name2fno['itemtype']]);
		elseif (strlen(trim($cols[$name2fno['itemtype']])))
			$itypes_new[]=trim($cols[$name2fno['itemtype']]);

		//statustypes
		if (getstatustypeidbyname($cols[$name2fno['status']])>=0) 
			$stypes_old[]=trim($cols[$name2fno['status']]);
		elseif (strlen(trim($cols[$name2fno['status']])))
			$stypes_new[]=trim($cols[$name2fno['status']]);

		//locations/areas
		$lr=getlocidsbynames($cols[$name2fno['location']],$cols[$name2fno['area']]);
		if ($lr[0]>=0)
			$loc_old[]=trim($cols[$name2fno['location']]." - ".$cols[$name2fno['area']]);
		else  {
			$loc_new[]=array('loc'=>trim($cols[$name2fno['location']]),'area'=>($cols[$name2fno['area']])); 
			$loc_new2[]=trim($cols[$name2fno['location']].":".$cols[$name2fno['area']]);
		}
		
	    //racks
        $rcheck=getrackarraybyname($cols[$name2fno['rack']]);
        //return array example    Array ( [id] => 1 [name] => H5 [area] => Row H [loc] => Ungar ) 
        if ($rcheck[0]>=0) {
            //rack exists - rack will import with existing area and location regardless of these values
            $rack_exists[]=trim($cols[$name2fno['rack']]." - ".$cols[$name2fno['area']]." - ".$cols[$name2fno['location']]);
            $rack_old[]=implode(' - ', $rcheck);
            //$rack_old[]=trim($rcheck[id]." (id): ".$rcheck[name]." (name): ".$rcheck[area]." (area): ".$rcheck[loc]." (loc)");
        }
        else {
            //rack does not exist - store area and location names for id lookup after location/locarea insert
            $rack_new[]=array('rack'=>trim($cols[$name2fno['rack']]), 'area'=>trim($cols[$name2fno['area']]), 'loc'=>trim($cols[$name2fno['location']]));
            $rack_new2[]=trim($cols[$name2fno['rack']]." : ".$cols[$name2fno['area']]." : ".$cols[$name2fno['location']]);
        }

	}

	echo "</tbody></table>\n";
	echo "</div>";
	?>

	<div style='float:left;clear:both; width:100%; margin-top:20px;'>
	    <div style='width:200px;height:200px;overflow:auto; float:left;text-align:left; clear:left;border:1px solid #ccc;margin-right:20px;'>
		<b>New H/W Manufacturers detected (will be inserted to the DB):</b><br>
		<hr>
		<?php 
		$hwman_new=array_iunique($hwman_new,SORT_STRING);
		foreach ($hwman_new as $hmn)
			echo "$hmn<br>\n";
		?>
		</div>

	    <div style='border:1px solid #ccc;width:200px;height:200px;overflow:auto; text-align:left;float:left;margin-left:20px;'>
		<b>New Users detected (will be inserted to the DB):</b><br>
		<hr>
		<?php
		$user_new=array_iunique($user_new,SORT_STRING);
		foreach ($user_new as $hmn)
			echo "$hmn<br>\n";
		?>
		</div>

	    <div style='border:1px solid #ccc;width:200px;height:200px;overflow:auto; text-align:left;float:left;margin-left:20px;'>
		<b>New Item Types detected (will be inserted to the DB):</b><br>
		<hr>
		<?php
		$itypes_new=array_iunique($itypes_new,SORT_STRING);
        if (count($itypes_new))
		foreach ($itypes_new as $itype)
			echo "$itype<br>\n";
		?>
		</div>

	    <div style='border:1px solid #ccc;width:200px;height:200px;overflow:auto; text-align:left;float:left;margin-left:20px;'>
		<b>Invalid Status Types detected (will NOT be inserted to the DB):</b><br>
		<hr>
		<?php
		$stypes_new=array_iunique($stypes_new,SORT_STRING);
		foreach ($stypes_new as $stype)
			echo "$stype<br>\n";
		?>
		</div>

	    <div style='border:1px solid #ccc;width:200px;height:200px;overflow:auto; text-align:left;float:left;margin-left:20px;'>
		<b>New Locations / Locarea detected (will be inserted to the DB):</b><br>
		<hr>
		<?php
		$loc_new2=array_iunique($loc_new2,SORT_STRING);
		foreach ($loc_new2 as $loc) {
			//echo "{$loc['loc']} - {$loc['area']}<br>\n";
			echo "$loc<br>\n";
		}
		?>
		</div>
		
        <div style='border:1px solid #ccc;width:200px;height:200px;overflow:auto; text-align:left;float:left;margin-left:20px;'>
        <b>New Racks (will be inserted into the DB):</b><br>
        <hr>
        <?php
        $rack_new2=array_iunique($rack_new2,SORT_STRING);
        foreach ($rack_new2 as $racknew) {
             echo "$racknew<br>\n";
        }
        ?>
        </div>
        
        <div style='border:1px solid #ccc;width:200px;height:200px;overflow:auto; text-align:left;float:left;margin-left:20px;'>
        <b>Existing Racks (from DB):</b><br>
        <hr>
        <?php
        $rack_old=array_iunique($rack_old,SORT_STRING);
        foreach ($rack_old as $rackid) {
             echo "$rackid<br>\n";
        }
        ?>
        </div>     
        
        <div style='border:1px solid #ccc;width:200px;height:200px;overflow:auto; text-align:left;float:left;margin-left:20px;'>
        <b>Existing Racks (import sheet):</b><br>
        <hr>
        <?php
        $rack_exists=array_iunique($rack_exists,SORT_STRING);
        foreach ($rack_exists as $racks) {
             echo "$racks<br>\n";
        }
        ?>
        </div>     
	</div>

	<div style='clear:both;text-align:center:width:100%; '>
		<?php if ($nextstep!=0) { ?>
		<form method=post name='importfrm' action='<?php echo $scriptname?>?action=<?php echo $action?>' enctype='multipart/form-data'>
		<input type=hidden name='nextstep' value='2'>
		<td colspan=2><input type=submit value='Import' ></td></tr>
		<input type=hidden name='delim' value='<?php echo $_POST['delim']?>'>
		<input type=hidden name='imfn' value='<?php echo $imfn?>'>
		<input type=hidden name='skip1st' value='<?php echo $_POST['skip1st']?>'>
		</form>
		<?php } ?>

		<form method=post name='importfrm' action='<?php echo $scriptname?>?action=<?php echo $action?>' enctype='multipart/form-data'>
		<input type=hidden name='nextstep' value='0'>
		<td colspan=2><input type=submit value='Back' ></td></tr>
		</form>
	</div>

<?php
}

if ($nextstep==2) {
	$imlines=file($imfn);
	//$hwm=getagenthwmanufacturers();
	echo "<b>Updating DB with=$imfn</b>";

	foreach ($imlines as $line_num => $line) {
		if ($line_num==0 && $_POST['skip1st']) 
			continue;

		$cols=explode($delim,$line);
		//hw manufacturer
		if (gethwmanufacturerbyname($cols[$name2fno['manufacturer']])!=-1) 
			$hwman_old[]=trim($cols[$name2fno['manufacturer']]);
		else 
			$hwman_new[]=trim($cols[$name2fno['manufacturer']]);

		//users
		if (getuserbyname($cols[$name2fno['username']])!=-1) 
			$user_old[]=trim($cols[$name2fno['username']]);
		else 
			$user_new[]=trim($cols[$name2fno['username']]);


		//itemtypes
		if (getitemtypeidbyname($cols[$name2fno['itemtype']])>=0) 
			$itypes_old[]=trim($cols[$name2fno['itemtype']]);
		else 
			$itypes_new[]=trim($cols[$name2fno['itemtype']]);

		//statustypes
		if (getstatustypeidbyname($cols[$name2fno['status']])>=0) 
			$stypes_old[]=trim($cols[$name2fno['status']]);
		else 
			$stypes_new[]=trim($cols[$name2fno['status']]);

		//locations/areas
		$lr=getlocidsbynames($cols[$name2fno['location']],$cols[$name2fno['area']]);

		if ($lr[0]>=0) 
			$loc_old[]=trim($cols[$name2fno['location']]." - ".$cols[$name2fno['area']]);
		else 
			$loc_new[]=array('loc'=>trim($cols[$name2fno['location']]),'area'=>($cols[$name2fno['area']])); 
		
        //racks
        $rcheck=getrackarraybyname($cols[$name2fno['rack']]);
        //return array example    Array ( [id] => 1 [name] => H5 [area] => Row H [loc] => Building ) 
        if ($rcheck[0]>=0) {
            //rack exists - rack will import with existing area and location
            $rack_old[]=implode(' - ', $rcheck);
        }
        else {
            //rack does not exist - store area and location ids for insert
            $rack_new[]=array('label'=>trim($cols[$name2fno['rack']]), 'areaid'=>trim($lr['locareaid']), 'locid'=>trim($lr['locid']));
        }
	}


	//add manufacturers
	$hwman_new=array_iunique($hwman_new,SORT_STRING);
	foreach ($hwman_new as $hwm) {
		$hwm=ucfirst($hwm);

		$sql="INSERT into agents (type,title) VALUEs ('8',:hwm)";
        $stmt=db_execute2($dbh,$sql,array('hwm'=>$hwm));
	}

	//add users
	$user_new=array_iunique($user_new,SORT_STRING);

	foreach ($user_new as $usr) {
		$usr=strtolower($usr);
		$sql="INSERT into users (username,usertype) VALUEs (:usr,1)";
        $stmt=db_execute2($dbh,$sql,array('usr'=>$usr));
	}

	//item types
	$itypes_new=array_iunique($itypes_new,SORT_STRING);
	foreach ($itypes_new as $itype) {
		$itype=strtolower($itype);
		$sql="INSERT into itemtypes (typedesc,hassoftware) VALUEs (:itype,1)";
        $stmt=db_execute2($dbh,$sql,array('itype'=>$itype));
	}

	//add locations/locareas
	foreach ($loc_new as $loca) {
		$location=$loca['loc'];
		$locarea=$loca['area'];
		//insert location if not already there
		$sql="INSERT INTO locations (name)
            SELECT :location WHERE NOT EXISTS (SELECT 1 FROM locations WHERE name = :location)";
        $stmt=db_execute2($dbh,$sql,array('location'=>$location));

		//insert locareaid
		$lr=getlocidsbynames($location,$locarea);
		if ($lr[0]<0 && strlen($locarea)) {
			$sql="INSERT INTO locareas (areaname,locationid) ".
			"values (:locarea, (SELECT id FROM locations WHERE name = :location)) ";
            $stmt=db_execute2($dbh,$sql,array('locarea'=>$locarea,'location'=>$location));
		}
	}

    //add racks
    $rack_new=array_iunique($rack_new,SORT_STRING);
    
    foreach ($rack_new as $rack) {
        $rlabel=$rack['label'];
        $rlocareaid=$rack['areaid'];
        $rlocid=$rack['locid'];
        
        //insert rack with generic stats
        $sql="INSERT INTO racks (label, locationid, locareaid, usize, model, depth) ".
        "values (:rlabel, :rlocationid, :rlocareaid, 50, 'Cabinet', 1000) ";
        $stmt=db_execute2($dbh,$sql,array('rlabel'=>$rlabel,'rlocationid'=>$rlocid, 'rlocareaid'=>$rlocareaid));
    }

	//add items
	foreach ($imlines as $line_num => $item) {
		if ($line_num==0 && $_POST['skip1st']) {
			echo "<br>Skipping first line<br>";
			continue;
		}

		if (!lineok($item,$delim))
			continue;

		$cols=explode($delim,$item);

		$lr=getlocidsbynames($cols[$name2fno['location']],$cols[$name2fno['area']]);
		if ($lr[0]<0) {
			echo "Location/locarea non existent: {$cols[$name2fno['location']]}/{$cols[$name2fno['area']]}<br>";
			$locid="";
			$locareaid="";
		}
		else {
			$locid=$lr['locid'];
			$locareaid=$lr['locareaid'];
		}
		//echo "<br>LR:{$cols[0]},{$cols[1]}=";print_r($lr); echo "<br>";

		$userid=getuseridbyname($cols[$name2fno['username']]);
		$ipv4=trim($cols[$name2fno['ipv4']]);
		$dnsname=trim($cols[$name2fno['dnsname']]);
		$comments=$cols[$name2fno['comments']];
		$manufacturerid=getagentidbyname($cols[$name2fno['manufacturer']]);
		$model=trim($cols[$name2fno['model']]);
		$sn=trim($cols[$name2fno['sn']]);
        $ispart=0;
        $rackmountable=1;
		$itemtypeid=getitemtypeidbyname($cols[$name2fno['itemtype']]);
		$status=getstatustypeidbyname($cols[$name2fno['status']]);
        $label=trim($cols[$name2fno['label']]);
		$function=$cols[$name2fno['function']];
		$cpu=$cols[$name2fno['cpu']];
		$ram=$cols[$name2fno['ram']];
		$hd=$cols[$name2fno['hd']];
		$cpuno=intval($cols[$name2fno['cpuno']]);
		$remadmip=$cols[$name2fno['remadmip']];
        $rackid=getrackidbyname($cols[$name2fno['rack']]);
		$rackposition=intval($cols[$name2fno['rackposition']]);
		$usize=intval($cols[$name2fno['usize']]);
		$rackposdepth=7;
		
		$umdecal=$cols[$name2fno['umdecal']];
		$admlogin=$cols[$name2fno['admlogin']];
		$admloginsc=$cols[$name2fno['admloginsc']];
		$pubip=$cols[$name2fno['pubip']]; 
		$hdtypes=$cols[$name2fno['hdtypes']]; 
		$owner=$cols[$name2fno['owner']];  
		$extadm=$cols[$name2fno['extadm']];




		$sql="INSERT into items ".
             "(userid,ipv4,dnsname,comments,manufacturerid,model,sn,ispart,rackmountable,itemtypeid,status,locationid,locareaid,label,function,cpu,ram,hd,cpuno,remadmip,rackid,rackposition,usize,rackposdepth, umdecal, admlogin, admloginsc, pubip, hdtypes, owner, extadm) ".
             " VALUES ".
             "(:userid,:ipv4,:dnsname,:comments,:manufacturerid,:model,:sn,:ispart,:rackmountable,:itemtypeid,:status,:locationid,:locareaid,:label,:function,:cpu,:ram,:hd,:cpuno,:remadmip,:rackid,:rackposition,:usize,:rackposdepth, :umdecal, :admlogin, :admloginsc, :pubip, :hdtypes, :owner, :extadm)";

        $stmt=db_execute2($dbh,$sql,
            array(
            'userid'=>$userid,
            'ipv4'=>$ipv4,
            'dnsname'=>$dnsname,
            'comments'=>$comments,
            'manufacturerid'=>$manufacturerid,
            'model'=>$model,
            'sn'=>$sn,
            'ispart'=>$ispart,
            'rackmountable'=>$rackmountable,
            'itemtypeid'=>$itemtypeid,
            'status'=>$status,
            'locationid'=>$locid,
            'locareaid'=>$locareaid,
            'label'=>$label,
            'function'=>$function,
			'cpu'=>$cpu,
			'ram'=>$ram,
			'hd'=>$hd,
			'cpuno'=>$cpuno,
			'remadmip'=>$remadmip,
			'rackid'=>$rackid,
		    'rackposition'=>$rackposition,
            'usize'=>$usize,
			'rackposdepth'=>$rackposdepth,
			
	        'umdecal'=>$umdecal,
            'admlogin'=>$admlogin,
            'admloginsc'=>$admloginsc,
            'pubip'=>$pubip,
            'hdtypes'=>$hdtypes,
            'owner'=>$owner,
            'extadm'=>$extadm,

            )
        );
		 //echo "<br>Isql=$sql<br>";
	}

	echo "\n<br><h2>Finished.</h2>\n";
}


function lineok ($line,$delim) {
    global $fno2name,$name2fno;

	$cols=explode($delim,$line);

	if (!strlen($cols[$name2fno['ip']])  //ip
		&& !strlen($cols[$name2fno['manufacturer']]) //manufact
		&& !strlen($cols[$name2fno['model']])) { //model
        echo "\n";
		echo "Skipping semi-empty line ($line)<br>";
        echo "Manuf: {$cols[$name2fno['manufacturer']]} <br>";
        echo "Model: {$cols[$name2fno['model']]} <br>";
        echo "Delim:$delim<br>\n";
        echo "cols:".print_r($cols)."<br>";
		return 0;
	}
	return 1;
}

function array_iunique($array) {
    if(!is_array($array))
        return null;
    elseif (!count($array))
        return array();
    else
    return array_intersect_key($array,array_unique(array_map(strtolower,$array)));
}



//echo "<p>NEXT2=$nextstep";
?>


</div> <!-- import1 -->

