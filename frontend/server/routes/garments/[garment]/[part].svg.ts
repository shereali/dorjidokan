const guides: Record<string, string> = {
  "body-length": '<path d="M105 62h90v260h-90z"/>', chest: '<path d="M94 135h112"/>', waist: '<path d="M102 205h96"/>', hip: '<path d="M98 258h104"/>',
  shoulder: '<path d="M94 82l34-22h44l34 22"/>', sleeve: '<path d="M96 82 40 108l26 68 38-26m100-68 56 26-26 68-38-26"/>', cuff: '<path d="m56 158 20-8m168 8-20-8"/>', collar: '<path d="M128 60q22 34 44 0v28h-44z"/>',
  outseam: '<path d="M112 70 82 326m106-256 30 256"/>', thigh: '<path d="M94 150h112"/>', knee: '<path d="M88 230h124"/>', bottom: '<path d="M80 326h48m44 0h48"/>', inseam: '<path d="M150 130 128 326m22-196 22 196"/>',
}
export default defineEventHandler((event) => {
  const part = String(getRouterParam(event, "part") || "").replace(/\.svg$/, "")
  setHeader(event, "Content-Type", "image/svg+xml")
  setHeader(event, "Cache-Control", "public, max-age=31536000, immutable")
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 360" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">${guides[part] || '<path d="M75 180h150"/>'}</svg>`
})
