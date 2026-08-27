const garments = {
  panjabi: { 'collar': 'COLLAR-PANJABI' },
  shirt: { 'collar': 'COLLAR-SHIRT' },
}
const garment = String('panjabi').toLowerCase()
const part = String('collar.svg').replace(/\.svg$/, '').toLowerCase()
const guide = garments[garment]?.[part] || garments.panjabi[part] || 'FALLBACK'
console.log('garment:', garment, 'part:', part, 'guide:', guide)
