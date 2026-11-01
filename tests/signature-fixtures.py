from pathlib import Path
from PIL import Image, ImageDraw
out = Path(__file__).resolve().parent / '.runtime'
out.mkdir(exist_ok=True)
for name in ('a', 'b'):
    image = Image.new('RGBA', (480, 120), (255, 255, 255, 0))
    draw = ImageDraw.Draw(image)
    draw.line([(20,85),(65,20),(40,95),(115,60),(135,85),(185,35),(160,90),(240,50),(260,80),(340,45),(430,80)], fill=(15,30,70,255), width=4)
    draw.text((300,100), 'Firma test ' + name, fill=(15,30,70,255))
    image.save(out / ('signature-' + name + '.png'))
Image.open(out / 'signature-a.png').convert('RGB').save(out / 'signature.jpg')
(out / 'invalid-signature.svg').write_text('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')
print('Synthetic signature fixtures created.')
