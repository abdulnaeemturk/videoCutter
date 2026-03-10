
---

# 2️⃣ `docs/index.md`

```markdown
# Example 0 – Advanced Multi-Cut Video Tool

This example demonstrates a **web-based video cutter** that allows users to:

- Upload a video
- Select multiple parts to remove
- Automatically rebuild the video

```

# Workflow
Upload Video<br>
↓<br>
Select Parts to Remove<br>
↓<br>
Calculate Remaining Segments<br>
↓<br>
Extract Segments (FFmpeg)<br>
↓<br>
Concatenate Segments<br>
↓<br>
Final Video<br>

---

# Video Upload

The user uploads a video using an HTML form.

Example PHP upload code:

```php
move_uploaded_file($_FILES["video"]["tmp_name"], $uploadedVideo);
```
```bash
ffprobe -v error -show_entries format=duration -of csv=p=0 input.mp4
```
**Calculating Segments to Keep**
If the user removes:<br>
10 → 15 seconds<br>
20 → 25 seconds<br>
The script keeps:<br>
0 → 10<br>
15 → 20<br>
25 → END<br>

**Extracting Segments**

FFmpeg command used:<br>
```bash
ffmpeg -ss START -i input.mp4 -t DURATION -c copy segment.mp4
```
**Concatenating Segments**

A list file is generated:
```mark
file 'part_0.mp4'
file 'part_1.mp4'
file 'part_2.mp4'
```

Merge command:
```bash
ffmpeg -f concat -safe 0 -i concat.txt -c copy final.mp4
```


