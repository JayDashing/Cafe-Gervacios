# Cafe Gervacios Refined Top-View Operational Guide

## Refined Material Palette

- Floor: quiet warm oak base with very subtle plank reveals
- Tables: honed light marble tops with walnut edge banding
- Chairs: dark charcoal leather/metal, visually secondary
- Booths: muted deep blue fabric, less saturated than the previous version
- Counter: warm walnut with light marble top
- Walls/windows: warm plaster, walnut trim, low-iron glass
- Lighting: smaller warm opal globe pendants

The floor is intentionally dominant now. Status colors are no longer large colored platforms.

## Muted Status Color Strategy

- `FREE`: soft sage green, thin outline and very faint halo
- `RESERVED`: muted amber/gold outline
- `OCCUPIED`: muted wine red outline
- `CLEANING`: slate blue/gray outline

Status indicators are now thin rings/rectangular outlines around tables with a low-opacity halo. They are visible enough for operations without making the layout look like a board game.

## Improved Spacing Strategy

- Rectangular table center spacing: about `3.10m`
- Booth center spacing: about `3.00m`
- Round table center spacing: about `3.40m`
- Main service aisle: about `1.25m`
- Restaurant shell widened to give the layout more breathing room
- Center dining, booths, round tables, counter, waiting, and kitchen zones are separated by subtle material shifts

## Table Scaling Adjustments

- Rectangular tables increased from about `1.10m x 0.78m` to `1.38m x 0.96m`
- Group tables increased to `1.58m x 0.98m`
- Round tables increased from `0.53m` radius to `0.68m` radius
- Counter seats increased slightly and spaced farther from round tables
- Click zones also expanded to remain tablet/laptop friendly

## Booth Redesign Approach

- Booth tables are larger and cleaner
- Booth backs are slimmer and less chunky
- Booth spacing is widened along the window side
- Booth blue is muted to read as upscale fabric instead of saturated game color
- Booth status indicators use restrained rectangular rings

## Operational Readability Improvements

- Tables are the visual priority
- Chairs support the layout but no longer dominate
- Floor zones are nearly transparent
- Labels are small tabletop plaques, not floating cards
- Service paths are visible but subdued
- Fixed camera keeps the whole layout understandable in one host-stand view

## Click-Safe Interaction Approach

Only `Interaction_Zones` should be raycast in Three.js. Visible table meshes, chairs, booths, lights, decor, plants, walls, and counter details are visual only.

Clickable targets:

- `Clickable_T1` through `Clickable_T8`
- `Clickable_Booth_A1`, `Clickable_Booth_A2`, `Clickable_Booth_A3`
- `Clickable_Counter_1`, `Clickable_Counter_2`, `Clickable_Counter_3`

The generator validates that click zones do not overlap before export.

## glTF / Three.js Export Notes

- Use `Cafe_Gervacios_Refined_TopView_Operational.glb`
- Use the exported `Camera_Fixed_2_5D_Operations`
- Disable orbit, rotation, and pan
- Allow only optional tightly clamped zoom
- Raycast only objects where `userData.cg_object_type === 'interaction_zone'`
- Production optimization: enable Draco or Meshopt after raycast QA
