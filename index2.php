<?php
$video = "sample.mp4";

// --- Create per-video cached thumbnails ---
$hash = md5_file($video);
$thumbDir = "thumbnails/".$hash."/";
if(!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);

$totalThumbs = 10;
$duration = floatval(shell_exec("ffprobe -v error -show_entries format=duration -of csv=p=0 $video"));

for($i=0; $i<$totalThumbs; $i++){
    $time = ($i/$totalThumbs) * $duration;
    $thumbFile = $thumbDir . "thumb_" . str_pad($i+1,3,'0',STR_PAD_LEFT) . ".jpg";
    if(!file_exists($thumbFile)){
        shell_exec("ffmpeg -ss $time -i $video -frames:v 1 -q:v 2 $thumbFile 2>&1");
    }
}

// --- Handle icon uploads ---
if(isset($_FILES['iconFile'])){
    $targetDir = "uploads/";
    if(!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    $targetFile = $targetDir . basename($_FILES["iconFile"]["name"]);
    move_uploaded_file($_FILES["iconFile"]["tmp_name"], $targetFile);
    echo json_encode(["file"=>$targetFile]);
    exit;
}

// --- Handle export ---
if(isset($_POST['segments'])){
    $segments = json_decode($_POST['segments'], true);
    $overlays = json_decode($_POST['overlays'], true);
    $videoPath = escapeshellarg($video);
    $tempFiles = [];
    $i = 0;

    // --- Create segments folder ---
    $segDir = "segments/";
    if(!is_dir($segDir)) mkdir($segDir, 0777, true);

    // --- Cut segments ---
    foreach($segments as $seg){
        $out = $segDir . "seg_$i.mp4";
        shell_exec("ffmpeg -ss {$seg['start']} -i $videoPath -t {$seg['duration']} -c copy $out 2>&1");
        $tempFiles[] = $out;
        $i++;
    }

    // --- Apply overlays ---
    $overlayTemp = [];
    foreach($tempFiles as $idx => $segFile){
        $filters = "";
        $inputs = "-i $segFile";
        $needOverlay = false;

        foreach($overlays as $o){
            $segStart = $segments[$idx]['start'];
            $segEnd = $segStart + $segments[$idx]['duration'];
            if($o['start'] < $segEnd && $o['end'] > $segStart){
                $needOverlay = true;
                $enableStart = max($o['start'], $segStart) - $segStart;
                $enableEnd = min($o['end'], $segEnd) - $segStart;
                $enable = "between(t,$enableStart,$enableEnd)";

                if($o['type']=="text" && isset($o['content'])){
                    $text = addslashes($o['content']);
                    $x = $o['x_perc']."*w";
                    $y = $o['y_perc']."*h";
                    $filters .= "drawtext=text='{$text}':x={$x}:y={$y}:fontsize=24:fontcolor=white:enable='{$enable}',";
                } elseif($o['type']=="icon" && isset($o['file']) && file_exists($o['file'])){
                    $inputs .= " -i ".$o['file'];
                    $x = $o['x_perc']."*main_w";
                    $y = $o['y_perc']."*main_h";
                    $filters .= "[0:v][1:v]overlay={$x}:{$y}:enable='{$enable}',";
                }
            }
        }

        $outFile = $segDir . "overlay_$idx.mp4";
        if($needOverlay){
            $filterCmd = rtrim($filters,',');
            shell_exec("ffmpeg $inputs -vf \"$filterCmd\" -c:v libx264 -preset ultrafast -crf 18 -c:a aac $outFile 2>&1");
        } else {
            copy($segFile, $outFile);
        }
        $overlayTemp[] = $outFile;
    }

    // --- Concatenate all segments with re-encoding to fix FFmpeg concat issue ---
    $listFile = $segDir . "list.txt";
    $fp = fopen($listFile,"w");
    foreach($overlayTemp as $f){
        fwrite($fp,"file '$f'\n");
    }
    fclose($fp);

    // --- Save final output in exports folder ---
    $webDir = "exports/";
    if(!is_dir($webDir)) mkdir($webDir, 0777, true);
    $finalOutput = $webDir . "final_".time().".mp4";

    shell_exec("ffmpeg -f concat -safe 0 -i $listFile -c:v libx264 -preset ultrafast -crf 18 -c:a aac $finalOutput 2>&1");

    if(file_exists($listFile)) unlink($listFile);

    if(file_exists($finalOutput)){
        echo "<h3 style='color:green'>Download Final Video:</h3>";
        echo "<a href='$finalOutput' download>".basename($finalOutput)."</a><br><br>";
        echo "<button onclick='deleteSegments()' style='padding:8px 20px;background:red;color:white;border:none;border-radius:5px;cursor:pointer;'>Delete All Segments</button>";
    } else {
        echo "<h3 style='color:red'>Error: File not created!</h3>";
    }
    exit;
}

// --- Handle Delete Segments ---
if(isset($_GET['delete_segments'])){
    $segDir = "segments/";
    if(is_dir($segDir)){
        $files = glob($segDir."*");
        foreach($files as $f) if(is_file($f)) unlink($f);
    }
    echo "All segments deleted!";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Professional Video Editor</title>
<style>
body{margin:0;padding:0;background:#111;color:#fff;font-family:Arial;}
#editorWrapper{width:900px;margin:20px auto;}
video{width:100%;border-radius:10px;position:relative;z-index:1;}
#timeline{width:100%;height:60px;background:#333;margin-top:10px;position:relative;border-radius:5px;cursor:pointer;overflow:hidden;}
#thumbnails{position:absolute;top:0;left:0;height:100%;width:100%;display:flex;z-index:0;overflow:hidden;}
#thumbnails img{height:100%;flex-shrink:0;}
.block{position:absolute;height:100%;opacity:.8;border-radius:4px;cursor:pointer;z-index:2;}
.handle{width:6px;background:#fff;position:absolute;top:0;height:100%;cursor:ew-resize;}
#controls button{padding:8px 20px;margin:5px;border:none;border-radius:5px;cursor:pointer;}
#controls button#modeBtn{background:#ff4444;color:#fff;}
#controls button#previewBtn{background:#2196f3;color:#fff;}
#controls button#exportBtn{background:#00c853;color:#fff;}
#controls button#addTextBtn{background:#ff9800;color:#fff;}
#controls button#addIconBtn{background:#9c27b0;color:#fff;}
.overlay{position:absolute;color:white;font-weight:bold;cursor:move;user-select:none;z-index:3;}
#playhead{position:absolute;top:0;width:2px;height:100%;background:yellow;z-index:5;pointer-events:none;}
</style>
</head>
<body>
<div id="editorWrapper">
<h2>Professional Video Editor</h2>

<video id="video" controls>
    <source src="<?php echo $video; ?>">
</video>

<div id="timeline">
    <div id="thumbnails"></div>
    <div id="playhead"></div>
</div>

<div id="controls">
<input type="file" id="iconFile" accept="image/*" style="display:none">
<button id="modeBtn">Mode: KEEP</button>
<button id="previewBtn">Preview Selected</button>
<button id="exportBtn">Export</button>
<button id="addTextBtn">Add Text</button>
<button id="addIconBtn">Add Icon</button>
</div>

<form method="post" id="form">
    <input type="hidden" name="segments" id="segmentsInput">
    <input type="hidden" name="overlays" id="overlaysInput">
</form>
</div>

<script>
let video=document.getElementById("video");
let timeline=document.getElementById("timeline");
let thumbnailsDiv=document.getElementById("thumbnails");
let playhead=document.getElementById("playhead");
let segments=[], overlays=[], duration=0, mode="keep";
let videoHash = "<?php echo $hash; ?>";

video.onloadedmetadata = ()=> { duration = video.duration; generateThumbnails(); };

function generateThumbnails(){
    let total = 10;
    thumbnailsDiv.innerHTML="";
    for(let i=0;i<total;i++){
        let img=document.createElement("img");
        let idx=(i+1).toString().padStart(3,'0');
        img.src = "thumbnails/"+videoHash+"/thumb_"+idx+".jpg";
        img.style.width = (timeline.offsetWidth/total)+"px";
        thumbnailsDiv.appendChild(img);
    }
}

// --- Timeline selection ---
let isDragging=false, startX, currentBlock=null, currentHandle=null;

timeline.onmousedown = e=>{
    let rect = timeline.getBoundingClientRect();
    if(e.target.classList.contains("handle")) { currentHandle=e.target; return; }
    isDragging=true;
    startX = e.clientX - rect.left;
    currentBlock = document.createElement("div");
    currentBlock.className="block";
    currentBlock.style.background=(mode==="keep")?"#00ff88":"#ff4444";
    currentBlock.style.left=startX+"px";
    currentBlock.style.width="0px";
    timeline.appendChild(currentBlock);
};

timeline.onmousemove = e=>{
    let rect = timeline.getBoundingClientRect();
    if(currentHandle){
        let parent=currentHandle.parentElement;
        let left=parseFloat(parent.style.left);
        let width=parseFloat(parent.style.width);
        if(currentHandle.dataset.side==="left"){
            let newLeft = e.clientX - rect.left;
            let newWidth = width + (left-newLeft);
            if(newWidth>5){ parent.style.left=newLeft+"px"; parent.style.width=newWidth+"px"; }
        } else {
            let newWidth = e.clientX - rect.left - left;
            if(newWidth>5) parent.style.width=newWidth+"px";
        }
        return;
    }
    if(!isDragging) return;
    let currX = e.clientX - rect.left;
    let left = Math.min(startX, currX);
    let width = Math.abs(currX - startX);
    currentBlock.style.left = left+"px";
    currentBlock.style.width = width+"px";
};

timeline.onmouseup = e=>{
    if(currentHandle){ currentHandle=null; return; }
    if(!isDragging) return;
    isDragging=false;
    let rect = timeline.getBoundingClientRect();
    let left = parseFloat(currentBlock.style.left);
    let width = parseFloat(currentBlock.style.width);
    if(width<5){ currentBlock.remove(); return; }
    let segStart = (left/rect.width)*duration;
    let segEnd = ((left+width)/rect.width)*duration;
    segments.push({start:Math.floor(segStart),duration:Math.floor(segEnd-segStart)});
    let leftHandle=document.createElement("div");
    leftHandle.className="handle"; leftHandle.style.left="0px"; leftHandle.dataset.side="left";
    let rightHandle=document.createElement("div");
    rightHandle.className="handle"; rightHandle.style.right="0px"; rightHandle.dataset.side="right";
    currentBlock.appendChild(leftHandle); currentBlock.appendChild(rightHandle);

    currentBlock.onclick=()=>{
        currentBlock.remove();
        segments=[];
        document.querySelectorAll(".block").forEach(b=>{
            let l=parseFloat(b.style.left);
            let w=parseFloat(b.style.width);
            let s = (l/rect.width)*duration;
            let e = ((l+w)/rect.width)*duration;
            segments.push({start:Math.floor(s),duration:Math.floor(e-s)});
        });
    };
};

// --- Mode toggle ---
document.getElementById("modeBtn").onclick=()=>{
    mode=(mode==="keep")?"delete":"keep";
    document.getElementById("modeBtn").innerText="Mode: "+mode.toUpperCase();
    document.getElementById("modeBtn").style.background=(mode==="keep")?"#ff4444":"#2196f3";
};

// --- Preview ---
let previewing=false, previewEnd=0, previewIndex=0;
document.getElementById("previewBtn").onclick = ()=>{
    if(segments.length===0){ alert("Select at least one KEEP segment."); return; }
    previewIndex=0; playSegment(segments[previewIndex]);
};
function playSegment(seg){
    video.currentTime=seg.start; previewEnd=seg.start+seg.duration; previewing=true; video.play();
}

// --- Update playhead & preview ---
video.addEventListener("timeupdate", ()=>{
    let rect = timeline.getBoundingClientRect();
    let percent = video.currentTime / duration;
    playhead.style.left = (percent * rect.width) + "px";

    if(previewing && video.currentTime>=previewEnd){
        previewing=false; video.pause();
        previewIndex++; 
        if(previewIndex<segments.length) playSegment(segments[previewIndex]);
    }
});

// --- Add Text / Icon Overlays ---
document.getElementById("addTextBtn").onclick=()=>{ addOverlay("text"); };
document.getElementById("addIconBtn").onclick=()=>{ document.getElementById("iconFile").click(); };
document.getElementById("iconFile").onchange=(e)=>{
    let file=e.target.files[0]; if(!file) return;
    let formData=new FormData();
    formData.append("iconFile",file);
    fetch("",{method:"POST",body:formData})
    .then(res=>res.json())
    .then(data=>{ if(data.file) addOverlay("icon",data.file); });
};

function addOverlay(type,file=null){
    let el=document.createElement('div'); el.className='overlay';
    if(type=="text"){ let t=prompt("Enter text:"); if(!t) return; el.innerText=t; }
    else{ el.innerText='⭐'; }
    el.style.left="50%"; el.style.top="50%"; el.style.transform="translate(-50%,-50%)";
    document.body.appendChild(el);
    let overlay={type:type,x_perc:0.5,y_perc:0.5,start:0,end:Math.floor(duration)};
    if(type=="text"){ overlay.content=el.innerText; }
    else if(file){ overlay.file=file; }
    overlays.push(overlay);
    makeDraggable(el,overlay);
    el.ondblclick=()=>{
        let s=parseFloat(prompt("Start time (s)",overlay.start))||0;
        let e=parseFloat(prompt("End time (s)",overlay.end))||duration;
        overlay.start=s; overlay.end=e;
        alert("Overlay visibility updated!");
    };
}
function makeDraggable(el,overlay){
    el.onmousedown=function(e){
        let rect=video.getBoundingClientRect();
        document.onmousemove=function(ev){
            overlay.x_perc=(ev.clientX-rect.left)/rect.width;
            overlay.y_perc=(ev.clientY-rect.top)/rect.height;
            el.style.left=(overlay.x_perc*100)+"%";
            el.style.top=(overlay.y_perc*100)+"%";
        };
        document.onmouseup=function(){ document.onmousemove=null; };
    };
}

// --- Export ---
document.getElementById("exportBtn").onclick=()=>{
    document.getElementById("segmentsInput").value=JSON.stringify(segments);
    document.getElementById("overlaysInput").value=JSON.stringify(overlays);
    document.getElementById("form").submit();
};

// --- Delete segments ---
function deleteSegments(){
    if(confirm("Are you sure you want to delete all cut segments?")){
        fetch("?delete_segments=1").then(res=>res.text()).then(msg=>{
            alert(msg);
        });
    }
};
</script>
</body>
</html>