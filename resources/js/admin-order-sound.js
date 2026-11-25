// Original synthetic cash-register sound, no external or licensed samples.
export function cashRegisterSamples(rate = 24000) {
  const out = new Float32Array(Math.ceil(rate * .72));
  let seed = 0x12345678, previous = 0;
  const ring = (t, start, f, decay, level) => {
    const x = t - start;
    return x < 0 ? 0 : level * Math.min(1, x / .003) * Math.exp(-x * decay) * Math.sin(2 * Math.PI * f * x);
  };
  for (let i = 0; i < out.length; i++) {
    const t = i / rate;
    seed = (Math.imul(seed,1664525) + 1013904223) >>> 0;
    const noise = seed / 2147483648 - 1, bright = (noise-previous)*.5; previous=noise;
    const click = t < .045 ? bright * .20 * Math.exp(-t*105) : 0;
    const slide = t >= .055 && t < .13 ? noise*.07*Math.sin(Math.PI*(t-.055)/.075) : 0;
    const latch = t >= .115 && t < .17 ? bright*.12*Math.exp(-(t-.115)*85) : 0;
    const bell = ring(t,.15,1320,11,.20)+ring(t,.15,2180,15,.12)+ring(t,.15,3025,20,.075)+ring(t,.15,4310,27,.045)
      +ring(t,.20,1760,9,.19)+ring(t,.20,2850,14,.10)+ring(t,.20,3960,23,.055);
    out[i] = Math.max(-.65,Math.min(.65,(click+slide+latch+bell)*Math.min(1,(.72-t)/.04)));
  }
  return out;
}
export function playCashRegister(context) {
  if (!context || context.state !== 'running') return false;
  try {
    const samples=cashRegisterSamples(context.sampleRate);
    const buffer=context.createBuffer(1,samples.length,context.sampleRate);
    buffer.copyToChannel(samples,0);
    const source=context.createBufferSource();
    source.buffer=buffer;source.connect(context.destination);source.start();
    source.onended=()=>source.disconnect();
    return true;
  } catch { return false; }
}
