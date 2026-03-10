<?php

$uploadedVideo = "";

if(isset($_FILES['video'])){
    $uploadDir = "uploads/";
    if(!is_dir($uploadDir)){
        mkdir($uploadDir);
    }

    $uploadedVideo = $uploadDir . basename($_FILES["video"]["name"]);
    move_uploaded_file($_FILES["video"]["tmp_name"], $uploadedVideo);
}

if(isset($_POST['cuts_json']) && isset($_POST['video_path'])){

    $inputVideo = escapeshellarg($_POST['video_path']);
    $cuts = json_decode($_POST['cuts_json'], true);

    if(!$cuts) die("No cuts received.");

    usort($cuts, function($a,$b){
        return $a['start'] - $b['start'];
    });

    $durationCmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $inputVideo";
    $videoDuration = floatval(shell_exec($durationCmd));

    $keepParts = [];
    $currentStart = 0;

    foreach($cuts as $cut){
        if($cut['start'] > $currentStart){
            $keepParts[] = [
                "start"=>$currentStart,
                "duration"=>$cut['start'] - $currentStart
            ];
        }
        $currentStart = $cut['end'];
    }

    if($currentStart < $videoDuration){
        $keepParts[] = [
            "start"=>$currentStart,
            "duration"=>$videoDuration - $currentStart
        ];
    }

    $tempFiles = [];
    $i = 0;

    foreach($keepParts as $part){
        $out = "part_$i.mp4";
        shell_exec("ffmpeg -ss {$part['start']} -i $inputVideo -t {$part['duration']} -c copy $out 2>&1");
        $tempFiles[] = $out;
        $i++;
    }

    $listFile = "concat.txt";
    $fp = fopen($listFile,"w");
    foreach($tempFiles as $file){
        fwrite($fp,"file '$file'\n");
    }
    fclose($fp);

    $final = "final_" . time() . ".mp4";
    shell_exec("ffmpeg -f concat -safe 0 -i $listFile -c copy $final 2>&1");

    echo "<h3 style='color:green;'>Download:</h3>";
    echo "<a href='$final' download>Download Final Video</a>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Advanced Video Cutter</title>
<style>
#timeline{
    width:700px;
    height:20px;
    background:#ddd;
    position:relative;
    margin-top:15px;
    cursor:crosshair;
}

.cut-block{
    position:absolute;
    height:100%;
    background:rgba(255,0,0,0.6);
}
button{margin-top:10px;}
</style>
</head>
<body>

<h2>Upload & Multi-Cut Video Tool</h2>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="video" accept="video/*" required>
    <button type="submit">Upload Video</button>
</form>

<?php if($uploadedVideo): ?>

<hr>

<video id="video" width="700" controls>
    <source src="<?php echo $uploadedVideo; ?>">
</video>

<div id="timeline"></div>

<button onclick="processCuts()">Export Video</button>
<button onclick="clearCuts()">Clear All Cuts</button>

<form method="post" id="cutForm">
    <input type="hidden" name="cuts_json" id="cutsInput">
    <input type="hidden" name="video_path" value="<?php echo $uploadedVideo; ?>">
</form>

<script>

let video = document.getElementById("video");
let timeline = document.getElementById("timeline");
let cuts = [];
let duration = 0;

video.onloadedmetadata = () => duration = video.duration;

let isDragging = false;
let startX = 0;
let currentBlock;

timeline.onmousedown = function(e){
    isDragging = true;
    startX = e.offsetX;

    currentBlock = document.createElement("div");
    currentBlock.className = "cut-block";
    currentBlock.style.left = startX + "px";
    timeline.appendChild(currentBlock);
};

timeline.onmousemove = function(e){
    if(!isDragging) return;

    let width = e.offsetX - startX;

    if(width < 0){
        currentBlock.style.left = e.offsetX + "px";
        currentBlock.style.width = Math.abs(width)+"px";
    } else {
        currentBlock.style.width = width+"px";
    }
};

timeline.onmouseup = function(e){
    if(!isDragging) return;
    isDragging = false;

    let rect = currentBlock.getBoundingClientRect();
    let timelineRect = timeline.getBoundingClientRect();

    let startPixel = rect.left - timelineRect.left;
    let endPixel = startPixel + rect.width;

    let startSec = Math.floor((startPixel/timeline.offsetWidth)*duration);
    let endSec = Math.floor((endPixel/timeline.offsetWidth)*duration);

    if(endSec - startSec < 1){
        timeline.removeChild(currentBlock);
        return;
    }

    cuts.push({start:startSec,end:endSec});

    // Click to remove block
    currentBlock.onclick = function(){
        timeline.removeChild(this);
        cuts = cuts.filter(c => !(c.start===startSec && c.end===endSec));
    };
};

function processCuts(){
    document.getElementById("cutsInput").value = JSON.stringify(cuts);
    document.getElementById("cutForm").submit();
}

function clearCuts(){
    cuts = [];
    document.querySelectorAll(".cut-block").forEach(el=>el.remove());
}

</script>

<?php endif; ?>

</body>
</html>