// Serves garment-part measurement guide layers for the GarmentPrototypeBuilder.
//
// Each garment type has its own silhouette anchors; the shared visual grammar
// (300×360 viewBox, rounded joins, two-unit stroke, front view) keeps layers
// composable on the builder's stage. Guides are construction lines in the same
// positions a tailor would measure, so a completed garment reads as a coherent
// outline rather than a generic template.

type GuideMap = Record<string, string>

const garments: Record<string, GuideMap> = {
  panjabi: {
    'body-length': '<path d="M92 50 35 100l30 78 28-18v165h114V160l28 18 30-78-57-50-28 25h-60z"/>',
    'chest': '<path d="M94 135h112"/>',
    'waist': '<path d="M102 205h96"/>',
    'hip': '<path d="M98 258h104"/>',
    'shoulder': '<path d="M94 82l34-22h44l34 22"/>',
    'sleeve': '<path d="M96 82 40 108l26 68 38-26m100-68 56 26-26 68-38-26"/>',
    'cuff': '<path d="m56 158 20-8m168 8-20-8"/>',
    'collar': '<path d="M128 60q22 34 44 0v28h-44z"/>',
  },
  shirt: {
    'body-length': '<path d="M94 50 40 104l28 76 30-16v146h124V164l30 16 28-76-54-54-34 24h-38z"/>',
    'chest': '<path d="M92 142h116"/>',
    'waist': '<path d="M100 210h100"/>',
    'shoulder': '<path d="M96 84l32-24h44l32 24"/>',
    'sleeve': '<path d="M96 84 42 112l24 62 40-24m102-66 54 28-24 62-40-24"/>',
    'cuff': '<path d="m52 158 22-10m174 10-22-10"/>',
    'collar': '<path d="M126 58q24 30 48 0v30h-48z"/>',
  },
  pant: {
    'outseam': '<path d="M116 70 84 326m100-256 32 256"/>',
    'waist': '<path d="M108 74h84"/>',
    'hip': '<path d="M104 130h92"/>',
    'thigh': '<path d="M96 190h108"/>',
    'knee': '<path d="M90 250h120"/>',
    'bottom': '<path d="M80 322h42m98 0h42"/>',
    'inseam': '<path d="M150 130 128 326m22-196 22 196"/>',
  },
  sherwani: {
    'body-length': '<path d="M96 44 36 106l32 84 34-20v146h116V170l34 20 32-84-60-62-40 30h-42z"/>',
    'chest': '<path d="M92 148h116"/>',
    'waist': '<path d="M100 216h100"/>',
    'hip': '<path d="M96 262h108"/>',
    'shoulder': '<path d="M96 78l36-26h36l36 26"/>',
    'sleeve': '<path d="M98 78 44 110l24 60 40-22m96-70 54 32-24 60-40-22"/>',
    'cuff': '<path d="m50 156 20-6m180 6-20-6"/>',
    'collar': '<path d="M126 54q24 30 48 0v26h-48z"/>',
    'front-opening': '<path d="M150 84v196"/>',
    'pocket': '<path d="M186 216h34v22h-34z"/>',
  },
}

export default defineEventHandler((event) => {
  const garment = String(getRouterParam(event, "garment") || "").toLowerCase()
  const part = String(getRouterParam(event, "part") || "").replace(/\.svg$/, "").toLowerCase()
  const guide = garments[garment]?.[part] || garments.panjabi[part] || '<path d="M75 180h150"/>'
  setHeader(event, "Content-Type", "image/svg+xml")
  setHeader(event, "Cache-Control", "public, max-age=31536000, immutable")
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 360" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">${guide}</svg>`
})
