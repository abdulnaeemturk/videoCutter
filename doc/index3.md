# index3.md
```markdown
# Example 3 – Professional Video Editor with Overlays

This advanced example adds **text and icon overlays** to video segments.

Features:
- Select segments to keep
- Add text overlays
- Add icon/image overlays
- Preview segments with overlays
- Export final video

# Workflow
Load Video<br>
↓<br>
Generate Thumbnails<br>
↓<br>
Select Segments<br>
↓<br>
Add Overlays (Text / Icons)<br>
↓<br>
Preview Segments<br>
↓<br>
Export with FFmpeg<br>
↓<br>
Final Video<br>

# Adding Overlays
**Text Overlay Example:**
```bash
ffmpeg -i segment.mp4 -vf "drawtext=text='Hello':x=50:y=50:enable='between(t,0,5)'" output.mp4
```
**Icon Overlay Example:**
```bash
ffmpeg -i segment.mp4 -i icon.png -filter_complex "[0:v][1:v] overlay=x=50:y=50:enable='between(t,0,5)'" output.mp4
```
- Overlays use percentage coordinates for flexible positioning
- Multiple overlays can be applied per segment

# Export and Concatenation
Segments with overlays are concatenated:
```bash
ffmpeg -f concat -safe 0 -i list.txt -c:v libx264 -c:a aac final.mp4
```
