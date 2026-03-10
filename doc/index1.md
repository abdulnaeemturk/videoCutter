# index1.md
```markdown
# Example 1 – Professional Video Cutter

This example demonstrates a **web-based video cutter** that allows users to:

- Load a sample video
- Select segments to keep
- Preview selected segments
- Export the final video

# Workflow
Load Video<br>
↓<br>
Select KEEP Segments<br>
↓<br>
Preview Selected Segments<br>
↓<br>
Extract Segments (FFmpeg)<br>
↓<br>
Concatenate Segments<br>
↓<br>
Final Video<br>

# Segment Selection
Segments are selected via a draggable timeline block in the UI.

**Timeline Interaction:**
- Drag to create a block
- Click block to remove
- Toggle mode (KEEP / DELETE)

**JavaScript Example:**
```javascript
segments.push({start:10,duration:5});
```

**FFmpeg Segment Extraction:**
```bash
ffmpeg -ss START -i input.mp4 -t DURATION -c copy segment.mp4
```

**Concatenation:**
```bash
ffmpeg -f concat -safe 0 -i list.txt -c copy final.mp4
```
```
```
