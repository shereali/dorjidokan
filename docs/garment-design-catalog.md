# Garment Design Catalog

All layers use a `300 × 360` viewBox, centered front view, rounded joins, two-unit stroke, no background, and shared shoulder/waist anchors. Export each named layer as an optimized SVG. The `frontend/server/routes/garments/[garment]/[part].svg.ts` route serves these layers data-driven; adding a garment requires catalog rows + assets only, never frontend logic changes.

## Panjabi

1. **Body Length** — straight long torso panel with softly rounded hem, full-length front silhouette to mid-calf.
2. **Chest** — subtle horizontal construction guide across upper torso at the widest chest point.
3. **Waist** — tapered guide at natural waist without changing anchors.
4. **Hip** — lower-width guide preserving the long silhouette.
5. **Shoulder** — symmetric sloped shoulder seam from collar to sleeve anchor.
6. **Sleeve** — full-length paired sleeves, minimal flat-vector treatment.
7. **Cuff** — narrow matching cuff bands at both sleeve ends.
8. **Collar** — front-view mandarin collar with a short centered opening.

## Shirt

1. **Body Length** — short-to-medium torso panel, hip-length hem, front view. A single centered button placket line runs from the collar to the hem.
2. **Chest** — horizontal construction guide at the fullest chest, slightly below the shoulder anchors.
3. **Waist** — subtle inward taper at natural waist; anchors unchanged.
4. **Shoulder** — symmetric sloped seam from collar to sleeve anchor, same slope grammar as Panjabi.
5. **Sleeve** — paired short-to-elbow sleeves with a slight outward taper; cuffs implied at the end.
6. **Cuff** — narrow straight cuff bands at both sleeve ends with a single button dot.
7. **Collar** — pointed two-piece collar lying flat, with the placket line descending from its center.

## Pant

1. **Outseam** — symmetric outer leg lines from waist to hem, slightly flared toward the bottom.
2. **Waist** — horizontal band across the top with a small centered front closure tick.
3. **Hip** — fuller guide line below the waist where hip circumference is widest.
4. **Thigh** — upper-leg guide, wider than the knee, matching the outseam slope.
5. **Knee** — horizontal construction line at mid-leg.
6. **Bottom** — hem guide line with a small opening notch on the outer edge.
7. **Inseam** — symmetric inner-leg lines from crotch to hem, converging toward the bottom.

## Sherwani

A long coat-style garment with a fitted torso flaring from the waist, standing collar, and full-length sleeves.

1. **Body Length** — long coat silhouette from the shoulder to below the knee, fitted chest, flared skirt from the waist, front opening seam down the center.
2. **Chest** — horizontal guide at the fullest chest across the fitted torso.
3. **Waist** — tapered waist guide marking the jacket's nip-in point; the skirt flare begins here.
4. **Hip** — lower guide at the hip where the coat's skirt starts to flare.
5. **Shoulder** — symmetric structured shoulder seam, slightly extended for a tailored coat look.
6. **Sleeve** — full-length paired sleeves following the coat silhouette, closing at the wrist.
7. **Cuff** — narrow cuff bands with a subtle button detail.
8. **Collar** — standing band collar with a small notch at the front; no lapel (Sherwani collar is distinct from a shirt collar).
9. **Front Opening** — vertical center seam from collar to hem with small button ticks down the line.
10. **Pocket** — low jetted pocket at the skirt line on the wearer's left, small rectangular outline.

---

These prompts are the source of truth for designers and image-generation tools. Adding a garment requires catalog rows and SVG assets only; frontend logic must remain unchanged. The seeder defines the authoritative part list per garment (see `backend/database/seeders/DatabaseSeeder.php`).
