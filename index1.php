<?php
$video = "sample.mp4";

// Handle Export
if(isset($_POST['segments'])){
    $segments = json_decode($_POST['segments'], true);
    $videoPath = escapeshellarg($video);
    $tempFiles = [];
    $i = 0;

    foreach($segments as $seg){
        $out = "seg_$i.mp4";
        shell_exec("ffmpeg -ss {$seg['start']} -i $videoPath -t {$seg['duration']} -c copy $out 2>&1");
        $tempFiles[] = $out;
        $i++;
    }

    // Create concat list
    $listFile = "list.txt";
    $fp = fopen($listFile,"w");
    foreach($tempFiles as $f){
        fwrite($fp,"file '$f'\n");
    }
    fclose($fp);

    $final = "final_".time().".mp4";
    shell_exec("ffmpeg -f concat -safe 0 -i $listFile -c copy $final 2>&1");

    echo "<h3 style='color:green'>Download Final Video:</h3>";
    echo "<a href='$final' download>$final</a>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Professional Video Cutter</title>
<style>
body{font-family:Arial;background:#111;color:#fff;text-align:center}
video{margin-top:20px;border-radius:10px;}
#timeline{width:800px;height:30px;background:#333;margin:20px auto;position:relative;border-radius:5px;cursor:pointer;}
.block{position:absolute;height:100%;opacity:.8;border-radius:4px;cursor:pointer;}
#modeBtn{background:#ff4444;color:#fff;padding:8px 20px;margin:5px;border:none;border-radius:5px;}
#exportBtn{background:#00c853;color:#fff;padding:8px 20px;margin:5px;border:none;border-radius:5px;}
#previewBtn{background:#2196f3;color:#fff;padding:8px 20px;margin:5px;border:none;border-radius:5px;}
</style>
</head>
<body>

<h2>Professional Video Cutter</h2>

<video id="video" width="800" controls>
    <source src="<?php echo $video; ?>">
</video>

<div id="timeline"></div>

<button id="modeBtn">Mode: KEEP</button>
<button id="previewBtn">Preview Selected</button>
<button id="exportBtn">Export</button>

<form method="post" id="form">
    <input type="hidden" name="segments" id="segmentsInput">
</form>

<script>
let video = document.getElementById("video");
let timeline = document.getElementById("timeline");
let modeBtn = document.getElementById("modeBtn");
let previewBtn = document.getElementById("previewBtn");
let exportBtn = document.getElementById("exportBtn");

let duration = 0;
let segments = [];
let mode = "keep";

video.onloadedmetadata = ()=> duration = video.duration;

// Toggle Keep/Delete mode
modeBtn.onclick = ()=>{
    mode = (mode==="keep")?"delete":"keep";
    modeBtn.innerText = "Mode: " + mode.toUpperCase();
    modeBtn.style.background = (mode==="keep")?"#ff4444":"#2196f3";
};

// Timeline drag
let startX, isDragging=false, currentBlock;
timeline.onmousedown = e=>{
    isDragging=true;
    startX=e.offsetX;
    currentBlock=document.createElement("div");
    currentBlock.className="block";
    currentBlock.style.background=(mode==="keep")?"#00ff88":"#ff4444";
    timeline.appendChild(currentBlock);
};
timeline.onmousemove = e=>{
    if(!isDragging) return;
    let width=e.offsetX-startX;
    currentBlock.style.left=(width<0?e.offsetX:startX)+"px";
    currentBlock.style.width=Math.abs(width)+"px";
};
timeline.onmouseup = e=>{
    if(!isDragging) return;
    isDragging=false;
    let rect=currentBlock.getBoundingClientRect();
    let tRect=timeline.getBoundingClientRect();
    let start=(rect.left-tRect.left)/timeline.offsetWidth*duration;
    let end=(rect.right-tRect.left)/timeline.offsetWidth*duration;
    if(end-start<1){ currentBlock.remove(); return; }

    if(mode==="keep"){
        segments.push({start:Math.floor(start),duration:Math.floor(end-start)});
    }

    // Click block to remove
    currentBlock.onclick=()=> {
        currentBlock.remove();
        segments=[];
        document.querySelectorAll(".block").forEach((b)=>{
            if(b.style.background==="rgb(0, 255, 136)"){
                let r=b.getBoundingClientRect();
                let s=(r.left-tRect.left)/timeline.offsetWidth*duration;
                let e=(r.right-tRect.left)/timeline.offsetWidth*duration;
                segments.push({start:Math.floor(s),duration:Math.floor(e-s)});
            }
        });
    };
};

// Preview selected segments
let previewing=false, previewEnd=0, previewIndex=0;
previewBtn.onclick = ()=>{
    if(segments.length===0){ alert("Select at least one KEEP segment."); return; }
    previewIndex=0;
    playSegment(segments[previewIndex]);
};

function playSegment(seg){
    video.currentTime = seg.start;
    previewEnd = seg.start + seg.duration;
    previewing = true;
    video.play();
}

video.addEventListener("timeupdate", ()=>{
    if(previewing && video.currentTime>=previewEnd){
        previewing=false;
        video.pause();
        previewIndex++;
        if(previewIndex<segments.length) playSegment(segments[previewIndex]);
    }
});

// Export
exportBtn.onclick=()=>{
    if(segments.length===0){ alert("Select at least one KEEP segment."); return; }
    document.getElementById("segmentsInput").value=JSON.stringify(segments);
    document.getElementById("form").submit();
};
</script>

</body>
</html>