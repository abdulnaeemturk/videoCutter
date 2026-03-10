# index2.md
```markdown
# Example 2 – Professional Video Cutter with Thumbnails

Adds **timeline thumbnails** to visualize video segments.

Features:
- Pre-generated thumbnails for the timeline
- Keep/Delete segment mode
- Preview selected segments
- Export final video

# Workflow
Load Video<br>
↓<br>
Generate Thumbnails (FFmpeg)<br>
↓<br>
Select Segments<br>
↓<br>
Preview Segments<br>
↓<br>
Extract Segments<br>
↓<br>
Concatenate Segments<br>
↓<br>
Final Video<br>

# Thumbnail Generation
**FFmpeg Command:**
```bash
ffmpeg -ss TIME -i input.mp4 -frames:v 1 -q:v 2 thumb_001.jpg
```
- Generate multiple thumbnails along the timeline
- Thumbnails help users visualize the video content

# Segment Selection
Segments are selected over thumbnails using draggable blocks.

# Export
Segments are extracted and concatenated using FFmpeg commands as in Example 1:
```bash
ffmpeg -ss START -i input.mp4 -t DURATION -c copy segment.mp4
ffmpeg -f concat -safe 0 -i list.txt -c copy final.mp4
```
