// Serves dynamic SVG vector guides for /svg/garments/*.svg

const guides: Record<string, string> = {
  // Suit 2-Piece
  'suit-length': '<path d="M70 45 25 100l26 70 28-16v146h142V154l28 16 26-70-45-55-38 28h-54z" fill="rgba(30, 41, 59, 0.1)" stroke="#3b82f6" stroke-width="2.5"/><path d="M150 45v255" stroke="#10b981" stroke-width="2" stroke-dasharray="4 3"/>',
  'suit-chest': '<path d="M74 135h152" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/><circle cx="74" cy="135" r="4" fill="#10b981"/><circle cx="226" cy="135" r="4" fill="#10b981"/>',
  'suit-waist': '<path d="M84 195h132" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/><circle cx="84" cy="195" r="4" fill="#10b981"/><circle cx="216" cy="195" r="4" fill="#10b981"/>',
  'suit-shoulder': '<path d="M72 75l38-30h80l38 30" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'suit-sleeve': '<path d="M72 75 25 102l26 68 34-20" stroke="#10b981" stroke-width="3" stroke-linecap="round"/><path d="M228 75 275 102l-26 68-34-20" stroke="#10b981" stroke-width="3" stroke-linecap="round"/>',
  'suit-back': '<path d="M78 105h144" stroke="#10b981" stroke-width="3" stroke-dasharray="4 3"/>',
  
  // Pants
  'pant-length': '<path d="M110 50 80 330m110-280 30 280" stroke="#3b82f6" stroke-width="2.5"/><path d="M150 50v280" stroke="#10b981" stroke-width="2" stroke-dasharray="4 3"/>',
  'pant-waist': '<path d="M102 54h96" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/><circle cx="102" cy="54" r="4" fill="#10b981"/><circle cx="198" cy="54" r="4" fill="#10b981"/>',
  'pant-hip': '<path d="M96 110h108" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'pant-thigh': '<path d="M90 170h120" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'pant-knee': '<path d="M86 230h128" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'pant-bottom': '<path d="M76 324h46m56 0h46" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'pant-high': '<path d="M150 54v75" stroke="#10b981" stroke-width="3" stroke-dasharray="3 3"/>',

  // Panjabi
  'panjabi-body': '<path d="M92 50 35 100l30 78 28-18v165h114V160l28 18 30-78-57-50-28 25h-60z" fill="rgba(30, 41, 59, 0.08)" stroke="#3b82f6" stroke-width="2.5"/><path d="M150 50v275" stroke="#10b981" stroke-width="2" stroke-dasharray="4 3"/>',
  'panjabi-chest': '<path d="M94 135h112" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/><circle cx="94" cy="135" r="4" fill="#10b981"/><circle cx="206" cy="135" r="4" fill="#10b981"/>',
  'panjabi-waist': '<path d="M102 205h96" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'panjabi-bottom': '<path d="M93 325h114" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'panjabi-shoulder': '<path d="M94 82l34-22h44l34 22" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'panjabi-sleeve': '<path d="M96 82 40 108l26 68 38-26m100-68 56 26-26 68-38-26" stroke="#10b981" stroke-width="3" stroke-linecap="round"/>',
  'panjabi-cuff': '<path d="m56 158 20-8m168 8-20-8" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'panjabi-collar': '<path d="M128 60q22 34 44 0v28h-44z" fill="none" stroke="#10b981" stroke-width="3"/>',

  // Shirt
  'shirt-length': '<path d="M94 50 40 104l28 76 30-16v146h124V164l30 16 28-76-54-54-34 24h-38z" fill="rgba(30, 41, 59, 0.08)" stroke="#3b82f6" stroke-width="2.5"/><path d="M150 50v246" stroke="#10b981" stroke-width="2" stroke-dasharray="4 3"/>',
  'shirt-chest': '<path d="M92 142h116" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'shirt-waist': '<path d="M100 210h100" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'shirt-shoulder': '<path d="M96 84l32-24h44l32 24" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'shirt-sleeve': '<path d="M96 84 42 112l24 62 40-24m102-66 54 28-24 62-40-24" stroke="#10b981" stroke-width="3" stroke-linecap="round"/>',
  'shirt-cuff': '<path d="m52 158 22-10m174 10-22-10" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'shirt-collar': '<path d="M126 58q24 30 48 0v30h-48z" fill="none" stroke="#10b981" stroke-width="3"/>',

  // Sherwani
  'sherwani-length': '<path d="M96 44 36 106l32 84 34-20v146h116V170l34 20 32-84-60-62-40 30h-42z" fill="rgba(30, 41, 59, 0.08)" stroke="#3b82f6" stroke-width="2.5"/>',
  'sherwani-chest': '<path d="M92 148h116" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'sherwani-waist': '<path d="M100 216h100" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'sherwani-hip': '<path d="M96 262h108" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'sherwani-shoulder': '<path d="M96 78l36-26h36l36 26" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'sherwani-sleeve': '<path d="M98 78 44 110l24 60 40-22m96-70 54 32-24 60-40-22" stroke="#10b981" stroke-width="3" stroke-linecap="round"/>',
  'sherwani-collar': '<path d="M126 54q24 30 48 0v26h-48z" stroke="#10b981" stroke-width="3"/>',

  // Waistcoat
  'waistcoat-length': '<path d="M95 55 50 100l22 45 28-10v120h110V135l28 10 22-45-45-45-38 22h-44z" fill="rgba(30, 41, 59, 0.08)" stroke="#3b82f6" stroke-width="2.5"/>',
  'waistcoat-chest': '<path d="M95 130h110" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'waistcoat-waist': '<path d="M100 185h100" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'waistcoat-shoulder': '<path d="M95 78l35-23h40l35 23" stroke="#10b981" stroke-width="3.5" stroke-linecap="round"/>',
  'waistcoat-collar': '<path d="M128 55q22 28 44 0v20h-44z" stroke="#10b981" stroke-width="3"/>',
}

export default defineEventHandler((event) => {
  const fileName = String(getRouterParam(event, "file") || "").replace(/\.svg$/, "").toLowerCase()
  const guide = guides[fileName] || '<path d="M75 180h150" stroke="#10b981" stroke-width="3"/>'
  setHeader(event, "Content-Type", "image/svg+xml")
  setHeader(event, "Cache-Control", "public, max-age=31536000, immutable")
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 360" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">${guide}</svg>`
})
