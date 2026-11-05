<?php
// Keep every editable package and sample placeholder in one place.
$sqbV2Placeholders = [
    'silver_space' => '[X GB / posting limits]',
    'gold_space' => '[higher limits]',
    'platinum_space' => '[highest limits]',
    'engine_credit' => '[x]',
];
$escV2 = static fn($value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SilverQueen: Your Engine in Every Barangay</title>


  <link rel="icon" href="assets/images/logo-symbol-white.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="preconnect" href="https://dlhnwzc5o0yyr.cloudfront.net" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue:wght@100;200;300;400;500;600;700;800;900&family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" />
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    html,body{overflow-x:clip;}
    body{background:#0a0a0a;color:#f5f5f5;font-family:'Inter',system-ui,-apple-system,sans-serif;min-height:100vh;-webkit-font-smoothing:antialiased;}
    .st-canvas{max-width:100%;margin:0 auto;padding:0 0;display:flex;flex-direction:column;gap:0;}
    .st-car-btn{width:36px;height:36px;border-radius:9999px;border:1px solid color-mix(in srgb,#f5f5f5 24%,transparent);background:transparent;color:#f5f5f5;opacity:.55;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:opacity .15s,border-color .15s;}
    .st-car-btn:hover{opacity:1;border-color:color-mix(in srgb,#f5f5f5 48%,transparent);}
    .st-canvas{padding-bottom:0;}
    .st-canvas>*:last-child{margin-bottom:0!important;padding-bottom:0!important;}
    .st-scene{margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);padding-left:calc(50vw - 50%);padding-right:calc(50vw - 50%);display:flex;flex-direction:column;gap:0;min-width:0;}
    .st-el{min-width:0;}
    .st-scene--media{position:relative;}
    .st-scene__bgwrap{position:absolute;inset:0;z-index:0;pointer-events:none;}
    .st-scene__bg{position:sticky;top:0;height:100vh;overflow:hidden;}
    .st-scene__bg>video,.st-scene__bg>canvas{width:100%;height:100%;object-fit:cover;display:block;}
    .st-scene--media>*:not(.st-scene__bgwrap){position:relative;z-index:1;}
    .st-scene__bg>.st-hero__pp{pointer-events:auto;}
    .st-scene__content{display:flex;flex-direction:column;width:100%;min-width:0;}
    @media (max-width:640px){.st-canvas>.st-el:not(.st-bleed):not(.st-custom),.st-scene>.st-el:not(.st-bleed):not(.st-custom){padding-left:16px;padding-right:16px;}}
    .st-text{margin:0;white-space:pre-line;overflow-wrap:break-word;}
    .st-quote{position:relative;}
    .st-quote::before{content:'\201C';position:absolute;left:.1em;top:0;padding-top:inherit;font-family:Georgia,'Times New Roman',serif;font-size:5.2em;line-height:.95;font-style:normal;opacity:.95;}
    .st-img{display:inline-block;height:auto;max-width:100%;vertical-align:top;}
    .st-sp-scene{display:flex;align-items:stretch;}
    .st-sp-scene.rev{flex-direction:row-reverse;}
    @media (max-width:720px){.st-sp-scene,.st-sp-scene.rev{flex-direction:column;}.st-sp-text{width:100%!important;min-height:auto!important;padding:32px 16px!important;}.st-sp-imgcol{width:100%!important;}.st-sp-img{height:60vh!important;}}
    .st-sp-text{width:50%;min-width:0;min-height:200vh;display:flex;flex-direction:column;justify-content:center;padding:60px 40px;box-sizing:border-box;}
    .st-sp-imgcol{width:50%;min-width:0;}
    .st-sp-img{position:sticky;top:0;height:100vh;overflow:hidden;}
    .st-sg{display:flex;align-items:stretch;}
    .st-sg.rev{flex-direction:row-reverse;}
    .st-sg-textcol{width:50%;min-width:0;}
    .st-sg-panel{position:sticky;top:0;min-height:100vh;display:flex;flex-direction:column;justify-content:center;padding:60px 40px;box-sizing:border-box;}
    .st-sg-panel-media{padding:0;display:block;height:100vh;}
    .st-sg-pinimg{position:relative;height:100%;overflow:hidden;}
    .st-sg-media{width:50%;min-width:0;}
    .st-sg-img{position:relative;height:100vh;overflow:hidden;}
    .st-sg-sec{min-height:100vh;display:flex;flex-direction:column;justify-content:center;padding:60px 40px;box-sizing:border-box;}
    @media (max-width:720px){.st-sg,.st-sg.rev{flex-direction:column;}.st-sg-textcol{width:100%!important;}.st-sg-panel{position:static;height:auto;min-height:0;padding:32px 16px;}.st-sg-panel-media{padding:0;height:60vh;}.st-sg-media{width:100%!important;}.st-sg-img{height:60vh!important;}.st-sg-sec{min-height:auto;padding:32px 16px;}}
    .st-proc{position:relative;width:100%;}
    .st-proc__inner{padding:56px 20px 64px;}
    .st-proc__head{margin-bottom:36px;}
    .st-proc__title{margin:0;}
    .st-proc__list{position:relative;}
    .st-proc__rail{display:none;}
    .st-proc__seg{position:absolute;left:0;top:0;width:1px;height:100%;background:currentColor;transform:scaleY(0);transform-origin:50% 0;will-change:transform;}
    .st-proc__steps{display:flex;flex-direction:column;gap:48px;max-width:560px;margin:0;padding:0;list-style:none;}
    .st-proc__step{display:flex;flex-direction:column;gap:16px;}
    .st-proc__sbody{display:flex;align-items:flex-start;gap:14px;}
    .st-proc__sbody:focus-visible{outline:1px solid currentColor;outline-offset:2px;}
    .st-proc__num{flex:none;margin-top:4px;font-size:13px;font-weight:500;letter-spacing:-0.01em;opacity:.5;font-variant-numeric:tabular-nums;}
    .st-proc__copy{min-width:0;}
    .st-proc__copy p{opacity:.68;}
    .st-proc__fig{position:relative;margin:0;aspect-ratio:var(--st-proc-ratio,4/5);overflow:hidden;background:rgba(127,127,127,0.12);}
    .st-proc__img{display:block;width:100%;height:100%;object-fit:cover;}
    .st-proc__media{display:none;}
    .st-proc__track{display:flex;flex-direction:column;height:100%;will-change:transform;}
    .st-proc__track .st-proc__fig{flex:none;height:100%;aspect-ratio:auto;}
    @media (min-width:900px){
      .st-proc:not(.st-proc--static){height:var(--st-proc-h,320vh);}
      .st-proc:not(.st-proc--static) .st-proc__stage{position:sticky;top:0;height:100vh;height:100svh;overflow:hidden;container-type:inline-size;}
      .st-proc:not(.st-proc--static) .st-proc__inner{--m:40px;--g:20px;--col:calc((100cqw - 2*var(--m) - 11*var(--g))/12);position:absolute;inset:0;display:flex;flex-direction:column;justify-content:center;padding:0 calc(var(--m) + 2*(var(--col) + var(--g)));}
      .st-proc:not(.st-proc--static) .st-proc__head{margin-bottom:clamp(40px,7vh,64px);}
      .st-proc:not(.st-proc--static) .st-proc__body{display:flex;align-items:center;}
      .st-proc--left:not(.st-proc--static) .st-proc__body{flex-direction:row-reverse;}
      .st-proc:not(.st-proc--static) .st-proc__list{flex:1.1;padding-right:10%;}
      .st-proc--left:not(.st-proc--static) .st-proc__list{padding-right:0;padding-left:10%;}
      .st-proc:not(.st-proc--static) .st-proc__rail{display:block;position:absolute;left:0;top:0;bottom:0;width:1px;background:color-mix(in srgb,currentColor 14%,transparent);}
      .st-proc:not(.st-proc--static) .st-proc__steps{gap:clamp(32px,5vh,48px);max-width:none;}
      .st-proc:not(.st-proc--static) .st-proc__step .st-proc__fig{display:none;}
      .st-proc:not(.st-proc--static) .st-proc__sbody{gap:16px;padding-left:24px;cursor:pointer;}
      .st-proc:not(.st-proc--static) .st-proc__num{margin-top:8px;}
      .st-proc:not(.st-proc--static) .st-proc__copy p{max-width:380px;}
      .st-proc:not(.st-proc--static) .st-proc__media{display:block;position:relative;flex:1;max-width:calc((100vh - 240px) * var(--st-proc-w,0.8));aspect-ratio:var(--st-proc-ratio,4/5);overflow:hidden;}
    }
    @media (min-width:900px) and (max-width:1199px){.st-proc:not(.st-proc--static) .st-proc__inner{padding:0 var(--m);}}
    .st-focus{position:relative;width:100%;height:var(--st-focus-h,300vh);}
    .st-focus__stage{position:sticky;top:0;height:100vh;height:100svh;box-sizing:border-box;display:flex;align-items:center;justify-content:center;padding:0 40px;}
    .st-focus__text{position:relative;max-width:15em;margin:0;}
    .st-focus__w{--d:1;display:inline-block;opacity:var(--st-focus-dim,.3);filter:blur(calc(var(--st-focus-blur,4px) * var(--d)));transition:opacity .35s cubic-bezier(.3,.9,.1,1),filter .35s cubic-bezier(.3,.9,.1,1);}
    .st-focus--fade .st-focus__w{opacity:calc(1 - (1 - var(--st-focus-dim,.3) / 2) * var(--d));filter:none;}
    .st-focus__w.is-on{opacity:1;filter:blur(0px);}
    .st-focus__frame{position:absolute;top:0;left:0;opacity:.5;pointer-events:none;}
    .st-focus--noframe .st-focus__frame{display:none;}
    .st-focus__c{position:absolute;top:0;left:0;width:.2em;height:.2em;box-sizing:border-box;border:0 solid currentColor;transition:transform .35s cubic-bezier(.3,.9,.1,1);will-change:transform;}
    .st-focus__c--tl{border-top-width:var(--st-focus-thick,2px);border-left-width:var(--st-focus-thick,2px);}
    .st-focus__c--tr{border-top-width:var(--st-focus-thick,2px);border-right-width:var(--st-focus-thick,2px);}
    .st-focus__c--bl{border-bottom-width:var(--st-focus-thick,2px);border-left-width:var(--st-focus-thick,2px);}
    .st-focus__c--br{border-bottom-width:var(--st-focus-thick,2px);border-right-width:var(--st-focus-thick,2px);}
    .st-focus--instant .st-focus__w,.st-focus--instant .st-focus__c{transition:none !important;}
    @media (prefers-reduced-motion:reduce){.st-focus__w{filter:none;transition:none;}.st-focus__c{transition:none;}}
    @media (max-width:767px){.st-focus__stage{padding:0 20px;}}
    .st-roll{position:relative;width:100%;height:var(--st-roll-h,300vh);}
    .st-roll__stage{position:sticky;top:0;height:100vh;height:100svh;box-sizing:border-box;display:grid;grid-template-columns:1fr 1fr;column-gap:.26em;align-items:center;padding:0 40px;font-size:clamp(32px,4vw,64px);font-weight:500;line-height:1;letter-spacing:-0.035em;}
    .st-roll--notag .st-roll__stage{grid-template-columns:1fr;justify-items:center;}
    .st-roll__window{justify-self:end;height:calc(var(--st-roll-visible,10) * 1em);overflow:hidden;-webkit-mask-image:linear-gradient(to bottom,transparent,#000 calc(var(--st-roll-fade,25) * 1%),#000 calc(100% - var(--st-roll-fade,25) * 1%),transparent);mask-image:linear-gradient(to bottom,transparent,#000 calc(var(--st-roll-fade,25) * 1%),#000 calc(100% - var(--st-roll-fade,25) * 1%),transparent);}
    .st-roll--notag .st-roll__window{justify-self:center;}
    .st-roll__list{margin:0;padding:0;list-style:none;text-align:right;will-change:transform;}
    .st-roll--notag .st-roll__list{text-align:center;}
    .st-roll__item{display:block;height:1em;white-space:nowrap;color:color-mix(in srgb,currentColor 22%,transparent);cursor:pointer;transition:color .3s ease-out;}
    .st-roll__item.is-on{color:currentColor;transition-duration:.1s;}
    .st-roll--drag .st-roll__list{cursor:grab;-webkit-user-select:none;user-select:none;}
    .st-roll--grabbing .st-roll__list{cursor:grabbing;}
    .st-roll__tagline{margin:0;white-space:nowrap;}
    @media (max-width:767px){.st-roll__stage{grid-template-columns:auto auto;justify-content:center;padding:0 20px;font-size:clamp(20px,6.4vw,32px);}}
    @media (prefers-reduced-motion:reduce){.st-roll__item{transition:none;}}
    .st-rail{position:relative;width:100%;}
    .st-rail__stage{position:relative;width:100%;box-sizing:border-box;padding:40px;}
    .st-rail__head{max-width:1280px;margin:0 auto 32px;}
    .st-rail__title{margin:0;}
    .st-rail__rail{position:relative;width:100%;max-width:1280px;margin:0 auto;}
    .st-rail__svg{display:block;width:100%;height:24px;overflow:visible;}
    .st-rail__svg--m{display:none;}
    .st-rail__path,.st-rail__ghost{fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round;}
    .st-rail__path{opacity:.78;}
    .st-rail__ghost{opacity:.28;stroke-dasharray:1 7;}
    .st-rail__bead{position:absolute;top:0;left:0;z-index:2;width:10px;height:10px;border-radius:50%;background:currentColor;box-shadow:0 0 0 5px var(--st-rail-bg,transparent);pointer-events:none;will-change:transform;}
    .st-rail__steps{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;column-gap:4%;margin:40px 0 0;padding:0;list-style:none;}
    .st-rail__step{position:relative;min-width:0;opacity:.42;transition:opacity .5s cubic-bezier(.3,.9,.1,1);}
    .st-rail__step.is-passed{opacity:.72;}
    .st-rail__step.is-on{opacity:1;}
    .st-rail__btn{display:block;width:100%;cursor:pointer;-webkit-tap-highlight-color:transparent;}
    .st-rail__btn:focus-visible{outline:1px solid currentColor;outline-offset:4px;}
    .st-rail__num{display:block;height:1em;font-size:13px;font-weight:500;line-height:1;letter-spacing:-0.01em;opacity:.62;font-variant-numeric:tabular-nums;}
    .st-rail__col{position:relative;display:inline-block;overflow:clip;width:.64em;height:1em;line-height:1;vertical-align:baseline;}
    .st-rail__strip{position:absolute;top:0;left:0;width:100%;transform:translateY(var(--hid,100%));transition:transform .6s cubic-bezier(.3,.9,.1,1);transition-delay:calc(var(--i,0) * -.05s);will-change:transform;}
    .st-rail__step.is-reached .st-rail__strip{transform:translateY(var(--tgt,0%));transition-delay:calc(.16s + var(--i,0) * .05s);}
    .st-rail__glyph{display:block;height:1em;line-height:1;text-align:center;}
    .st-rail__copy{margin-top:12px;}
    .st-rail__copy p{opacity:.62;}
    .st-rail__media{display:block;width:100%;margin-top:16px;aspect-ratio:var(--st-rail-ratio,4/3);overflow:hidden;transform-origin:50% 0;opacity:0;transform:scale(.9);transition:opacity .48s cubic-bezier(.3,.9,.1,1),transform .48s cubic-bezier(.3,.9,.1,1);will-change:transform,opacity;}
    .st-rail__step.is-reached .st-rail__media{opacity:1;transform:none;transition-duration:.8s;transition-delay:.08s;}
    .st-rail__img{display:block;width:100%;height:100%;object-fit:cover;}
    @media (min-width:768px){.st-rail{height:var(--st-rail-h,300vh);}.st-rail__stage{position:sticky;top:0;min-height:100vh;min-height:100svh;display:grid;align-content:center;}}
    @media (max-width:767px){.st-rail__stage{padding:20px;}.st-rail__rail{padding-left:72px;}.st-rail__svg--d{display:none;}.st-rail__svg--m{display:block;position:absolute;top:0;left:0;width:72px;height:100%;}.st-rail__steps{grid-auto-flow:row;grid-auto-rows:auto;row-gap:48px;column-gap:0;margin-top:0;}}
    @media (prefers-reduced-motion:reduce){.st-rail__step,.st-rail__strip,.st-rail__media{transition:none;}}
    .st-strip{position:relative;display:flex;align-items:stretch;width:100%;height:var(--st-strip-h,100vh);overflow:hidden;background:#101010;outline:none;touch-action:pan-y;--st-strip-m:40px;}
    .st-strip:focus-visible{outline:2px solid #fff;outline-offset:-2px;}
    .st-strip__panel{position:relative;flex:1 1 0;min-width:0;height:100%;overflow:hidden;cursor:pointer;will-change:flex-grow;}
    .st-strip__panel:focus-visible{outline:2px solid #fff;outline-offset:-2px;z-index:5;}
    .st-strip__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;transform-origin:center;transform:scale(1.25);will-change:transform;user-select:none;-webkit-user-drag:none;pointer-events:none;}
    .st-strip__shade{position:absolute;inset:0;z-index:1;background:rgba(10,10,10,.18);-webkit-backdrop-filter:blur(var(--st-strip-blur,0px));backdrop-filter:blur(var(--st-strip-blur,0px));pointer-events:none;}
    .st-strip__scrim{position:absolute;inset:0;z-index:1;background:linear-gradient(to top,rgba(10,10,10,.6) 0%,rgba(10,10,10,.05) 45%,rgba(10,10,10,.2) 100%);opacity:0;pointer-events:none;}
    .st-strip__text{position:absolute;left:var(--st-strip-m);right:var(--st-strip-m);bottom:var(--st-strip-m);z-index:2;display:flex;flex-direction:column;gap:8px;pointer-events:none;}
    .st-strip__title,.st-strip__label{position:relative;display:block;margin:0;overflow-y:hidden;overflow-x:visible;white-space:nowrap;}
    .st-strip__title{padding-bottom:.18em;margin-bottom:-.18em;color:rgba(255,250,242,.92);}
    .st-strip__label{padding-bottom:.2em;margin-bottom:-.2em;color:rgba(255,255,255,.78);}
    .st-strip__title-in,.st-strip__label-in{display:block;transform:translateY(110%);will-change:transform;}
    .st-strip__vlabel{position:absolute;left:var(--st-strip-m);bottom:var(--st-strip-m);z-index:2;transform:rotate(180deg);writing-mode:vertical-rl;overflow:hidden;pointer-events:none;}
    .st-strip__vlabel-in{display:block;font-size:19px;font-weight:400;letter-spacing:-.01em;color:rgba(255,255,255,.78);white-space:nowrap;transform:translateX(110%);will-change:transform;}
    .st-strip--nolabels .st-strip__vlabel{display:none;}
    @media (max-width:767px){.st-strip{flex-direction:column;height:var(--st-strip-h,100vh);--st-strip-m:20px;}.st-strip__vlabel{display:none;}.st-strip__panel{min-height:0;}.st-strip__title{font-size:clamp(22px,7vw,34px)!important;}}
    .st-wipe{position:relative;width:100%;height:var(--st-wipe-h,500vh);--st-wipe-m:40px;}
    .st-wipe__stage{position:sticky;top:0;width:100%;height:100vh;overflow:hidden;background:var(--st-wipe-bg,#101010);}
    .st-wipe__bg{position:absolute;inset:0;z-index:1;}
    .st-wipe__layer{position:absolute;inset:0;clip-path:inset(0 0 calc(var(--st-clip,0)*1%) 0);}
    .st-wipe__bg-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transform:scale(1.1);transform-origin:50% 50%;filter:brightness(.55) saturate(.92) blur(12px);}
    .st-wipe__card{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(var(--st-wipe-card,47.5vw),88vw);aspect-ratio:3/2;border-radius:12px;overflow:hidden;z-index:2;}
    .st-wipe__card-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
    .st-wipe__blank{position:absolute;inset:0;background:rgba(127,127,127,.12);}
    .st-wipe__progress{position:absolute;top:28px;left:var(--st-wipe-m);right:var(--st-wipe-m);height:1px;background:rgba(255,255,255,.18);z-index:3;}
    .st-wipe__fill{position:absolute;inset:0;background:rgba(255,255,255,.85);transform:scaleX(0);transform-origin:left center;will-change:transform;}
    .st-wipe__titles{position:absolute;top:44px;left:var(--st-wipe-m);right:var(--st-wipe-m);display:grid;grid-template-columns:repeat(var(--st-wipe-n,4),1fr);z-index:3;pointer-events:none;}
    .st-wipe__title{grid-row:1;position:relative;margin:0;min-width:0;}
    .st-wipe__line{position:relative;display:inline-block;overflow-x:visible;overflow-y:hidden;padding-bottom:.2em;margin-bottom:-.2em;}
    .st-wipe__char{position:relative;display:inline-block;will-change:transform;}
    .st-wipe__number{position:absolute;left:var(--st-wipe-m);bottom:32px;font-size:84px;font-weight:300;letter-spacing:-0.02em;line-height:1;color:rgba(255,255,255,.92);z-index:3;pointer-events:none;}
    .st-wipe__digit{position:relative;display:inline-block;overflow:hidden;height:1em;width:.52em;vertical-align:top;}
    .st-wipe__roller{position:absolute;left:0;top:0;display:flex;flex-direction:column;transition:transform .8s cubic-bezier(.87,0,.13,1);will-change:transform;}
    .st-wipe__roller span{display:block;height:1em;line-height:1;}
    .st-wipe__descs{position:absolute;right:var(--st-wipe-m);bottom:40px;width:340px;z-index:3;pointer-events:none;}
    .st-wipe__desc{position:absolute;right:0;bottom:0;width:100%;margin:0;overflow:hidden;}
    .st-wipe__desc-in{display:block;will-change:transform;}
    @media (max-width:767px){.st-wipe{--st-wipe-m:20px;}.st-wipe__titles{display:block;top:40px;}.st-wipe__title{position:absolute;left:0;top:0;}.st-wipe__number{left:auto;right:var(--st-wipe-m);top:40px;bottom:auto;font-size:44px;}.st-wipe__card{width:calc(100% - 2*var(--st-wipe-m));}.st-wipe__descs{left:var(--st-wipe-m);right:auto;bottom:24px;width:calc(100% - 2*var(--st-wipe-m));max-width:360px;}.st-wipe__desc{font-size:14px!important;}}
    @media (prefers-reduced-motion:reduce){.st-wipe__roller,.st-wipe__char,.st-wipe__desc-in{transition:none!important;}}
    .st-gch{position:relative;width:100%;--st-gch-m:40px;--st-gch-line:color-mix(in srgb,var(--st-gch-ink,#101010) 14%,transparent);padding:var(--st-gch-pad,0px) 0;color:var(--st-gch-ink,#101010);}
    .st-gch__rows{list-style:none;margin:0 var(--st-gch-m);padding:0;}
    .st-gch__row{position:relative;display:grid;grid-template-columns:3fr 6fr 3fr;border-bottom:1px solid var(--st-gch-line);}
    .st-gch__row--first{border-top:1px solid var(--st-gch-line);}
    .st-gch__stage{grid-column:2;grid-row:1;border-left:1px solid var(--st-gch-line);border-right:1px solid var(--st-gch-line);display:flex;align-items:center;justify-content:center;padding:0 32px;}
    .st-gch__text{display:flex;flex-direction:column;align-items:center;gap:26px;text-align:center;}
    .st-gch__label-wrap,.st-gch__headline{overflow-x:visible;overflow-y:clip;padding-bottom:.18em;margin-bottom:-.18em;}
    .st-gch__label{margin:0;display:flex;align-items:baseline;justify-content:center;gap:4px;opacity:.55;will-change:transform;}
    .st-gch__index{font-variant-numeric:tabular-nums;}
    .st-gch__dot{padding:0 6px;}
    .st-gch__headline{margin:0;max-width:20ch;text-wrap:balance;opacity:.82;}
    .st-gch__line{display:block;will-change:transform;}
    .st-gch__figure{grid-column:3;grid-row:1;margin:0 calc(-1*var(--st-gch-m)) 0 0;display:flex;align-items:flex-end;overflow:clip;}
    .st-gch__row--alt .st-gch__figure{grid-column:1;margin:0 0 0 calc(-1*var(--st-gch-m));}
    .st-gch__media{position:relative;width:100%;aspect-ratio:3/4;overflow:clip;background:color-mix(in srgb,var(--st-gch-ink,#101010) 6%,transparent);}
    .st-gch__img{display:block;width:100%;height:100%;object-fit:cover;transform:scale(1.15);will-change:transform;}
    .st-gch__overlay{position:absolute;inset:0;display:grid;grid-template-columns:3fr 6fr 3fr;margin:0 var(--st-gch-m);pointer-events:none;}
    .st-gch__sticky{grid-column:2;position:sticky;top:var(--st-gch-pad,0px);height:var(--st-gch-row-h,100vh);display:flex;align-items:center;justify-content:center;}
    .st-gch__slot{position:relative;width:100%;}
    .st-gch__slot .st-gch__text{position:absolute;left:32px;right:32px;top:50%;transform:translateY(-50%);}
    @media (max-width:1023px){.st-gch{--st-gch-m:20px;}.st-gch__overlay{display:none;}.st-gch__row{grid-template-columns:1fr;}.st-gch__stage{grid-column:1;grid-row:1;border-left:0;border-right:0;padding:72px 8px 48px;}.st-gch__figure,.st-gch__row--alt .st-gch__figure{grid-column:1;grid-row:2;margin:0;padding:0 0 56px;justify-content:center;}.st-gch__row--first{border-top:0;}.st-gch__row--last{border-bottom:0;}}
    @media (prefers-reduced-motion:reduce){.st-gch__label,.st-gch__line{transition:none!important;}.st-gch__img{transform:none!important;}}
    .st-faq{width:100%;}
    .st-faq__filters{position:absolute;width:0;height:0;overflow:hidden;pointer-events:none;}
    .st-faq__list{display:flex;flex-direction:column;gap:8px;}
    .st-faq__row{position:relative;}
    .st-faq__goo{position:absolute;inset:0;z-index:0;pointer-events:none;}
    .st-faq__goo-t,.st-faq__goo-p{position:absolute;top:0;left:0;width:100%;background:var(--st-faq-pill,rgba(128,128,128,.14));will-change:transform;}
    .st-faq__goo-p{border-radius:16px;}
    .st-faq__tcrisp{position:absolute;top:0;left:0;right:0;z-index:3;background:var(--st-faq-pill,rgba(128,128,128,.14));pointer-events:none;transition:background-color .3s cubic-bezier(.7,0,.16,1);}
    .st-faq__trigger:hover+.st-faq__tcrisp{background:var(--st-faq-pill-hover,rgba(128,128,128,.2));}
    .st-faq__pcrisp{position:absolute;top:0;left:0;z-index:1;background:var(--st-faq-pill,rgba(128,128,128,.14));border-radius:16px;pointer-events:none;will-change:transform;}
    .st-faq__trigger{position:relative;z-index:4;display:flex;align-items:center;gap:14px;width:100%;min-height:56px;padding:16px 22px;background:transparent;border:0;cursor:pointer;text-align:left;color:inherit;font:inherit;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
    .st-faq__trigger:focus{outline:none;}
    .st-faq__trigger:focus-visible{outline:2px solid currentColor;outline-offset:2px;border-radius:999px;}
    .st-faq__index{flex-shrink:0;min-width:22px;font-size:13px;font-weight:500;opacity:.55;font-variant-numeric:tabular-nums;}
    .st-faq__q{flex:1;min-width:0;}
    .st-faq__icon{width:16px;height:16px;flex-shrink:0;opacity:.78;transform-origin:50% 50%;will-change:transform;}
    .st-faq__panel{position:relative;z-index:2;height:0;overflow:hidden;will-change:height;}
    .st-faq__inner{margin-top:10px;padding:18px 22px 20px;}
    .st-faq__amask{overflow:clip;}
    .st-faq__a{margin:0;opacity:0;transform:translateY(100%);will-change:transform,opacity;}
    @media (max-width:767px){.st-faq__trigger{padding:14px 18px;gap:12px;}.st-faq__inner{padding:14px 18px 18px;}}
    @media (max-width:479px){.st-faq__index{display:none;}}
    .st-fqc{width:100%;--st-fqc-gap:24px;--st-fqc-pad:34px;--st-fqc-r:26px;--st-fqc-w:calc((100% - 2.4*var(--st-fqc-gap))/3.4);}
    .st-fqc__head{display:flex;align-items:flex-start;justify-content:space-between;gap:40px;margin-bottom:40px;}
    .st-fqc__title{margin:0;max-width:60%;overflow-wrap:break-word;}
    .st-fqc__hl{color:var(--st-fqc-accent);}
    .st-fqc__aside{display:flex;flex-direction:column;align-items:flex-start;gap:16px;max-width:400px;padding-top:8px;}
    .st-fqc__intro{margin:0;}
    .st-fqc__nav{display:flex;gap:8px;}
    .st-fqc__btn{width:44px;height:44px;border-radius:50%;border:1px solid color-mix(in srgb,currentColor 28%,transparent);background:transparent;color:inherit;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;padding:0;font:inherit;transition:background-color .25s,color .25s,border-color .25s;-webkit-tap-highlight-color:transparent;}
    .st-fqc__btn svg{width:18px;height:18px;}
    .st-fqc__btn--next{background:var(--st-fqc-ink);color:var(--st-fqc-bg);border-color:var(--st-fqc-ink);}
    .st-fqc__btn:hover{background:var(--st-fqc-ink);color:var(--st-fqc-bg);border-color:var(--st-fqc-ink);}
    .st-fqc__btn--next:hover{background:var(--st-fqc-active);color:var(--st-fqc-on);border-color:var(--st-fqc-active);}
    .st-fqc__btn:focus{outline:none;}.st-fqc__btn:focus-visible{outline:2px solid currentColor;outline-offset:2px;}
    .st-fqc__viewport{overflow:hidden;}
    .st-fqc__track{display:flex;gap:var(--st-fqc-gap);transform:translate3d(var(--st-fqc-x,0px),0,0);transition:transform .7s cubic-bezier(.65,0,.35,1);will-change:transform;}
    .st-fqc__card{position:relative;flex:0 0 var(--st-fqc-w);width:var(--st-fqc-w);height:var(--st-fqc-h,420px);min-height:300px;border-radius:var(--st-fqc-r);background:var(--st-fqc-tint);color:inherit;box-sizing:border-box;cursor:pointer;overflow:hidden;transition:background-color .55s cubic-bezier(.65,0,.35,1),color .55s cubic-bezier(.65,0,.35,1);-webkit-tap-highlight-color:transparent;}
    .st-fqc__card:hover{background:var(--st-fqc-tint-hover);}
    .st-fqc__card{--st-fqc-qc:color-mix(in srgb,var(--st-fqc-ink) 62%,var(--st-fqc-bg));--st-fqc-ac:var(--st-fqc-on);}
    .st-fqc__card.is-on,.st-fqc__card.is-on:hover{background:var(--st-fqc-active);color:var(--st-fqc-on);--st-fqc-qc:var(--st-fqc-on);--st-fqc-ac:color-mix(in srgb,var(--st-fqc-on) 84%,transparent);}
    .st-fqc__card:focus{outline:none;}.st-fqc__card:focus-visible{outline:2px solid currentColor;outline-offset:3px;}
    .st-fqc__q{position:absolute;left:var(--st-fqc-pad);right:var(--st-fqc-pad);top:var(--st-fqc-pad);margin:0;transform:translate3d(0,var(--q-y,0px),0);transition:transform .7s cubic-bezier(.65,0,.35,1),color .55s cubic-bezier(.65,0,.35,1);}
    .st-fqc__card.is-on .st-fqc__q{transform:none;}
    .st-fqc__a{position:absolute;left:var(--st-fqc-pad);right:var(--st-fqc-pad);top:var(--a-top,50%);margin:0;opacity:0;visibility:hidden;transform:translate3d(0,14px,0);transition:opacity .3s ease,transform .3s ease,visibility 0s .3s;}
    .st-fqc__card.is-on .st-fqc__a{opacity:1;visibility:visible;transform:none;transition:opacity .5s ease .28s,transform .6s cubic-bezier(.22,1,.36,1) .28s,visibility 0s;}
    @media (max-width:1023px){.st-fqc{--st-fqc-w:calc((100% - 1.2*var(--st-fqc-gap))/2.2);}}
    @media (max-width:767px){.st-fqc{--st-fqc-w:84%;--st-fqc-gap:14px;--st-fqc-pad:24px;--st-fqc-r:22px;}.st-fqc__head{flex-direction:column;gap:18px;margin-bottom:28px;}.st-fqc__title{max-width:none;}.st-fqc__aside{padding-top:0;}}
    @media (prefers-reduced-motion:reduce){.st-fqc__track,.st-fqc__card,.st-fqc__q,.st-fqc__a{transition:none!important;}}
    .st-chart{width:min(80vw,100%);max-width:1100px;}
    @media (max-width:640px){.st-chart{width:min(96vw,100%);max-width:none;}}
    .st-chart .st-ch-bars{height:min(56vh,500px);}
    .st-chart .st-ch-pie{width:min(38vw,360px);height:min(38vw,360px);}
    @media (max-width:640px){.st-chart .st-ch-bars{height:min(48vh,340px);}.st-chart .st-ch-pie{width:62vw;height:62vw;}}
    .st-chpin{height:220vh;}
    .st-chpin__stick{position:sticky;top:0;height:100vh;display:flex;flex-direction:column;align-items:stretch;justify-content:center;min-height:0;}
    @media (max-width:640px){.st-chpin__stick{padding-left:16px;padding-right:16px;}}
    .st-ch-bars{display:flex;align-items:flex-end;gap:14px;height:240px;}
    .st-ch-col{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;height:100%;}
    .st-ch-track{flex:1;width:100%;display:flex;align-items:flex-end;}
    .st-ch-bar{width:100%;height:calc(var(--h,0%) * var(--p,0));border-radius:6px 6px 0 0;background:var(--bar);transition:height .12s linear;}
    .st-ch-val{margin-top:8px;font-size:12px;font-weight:600;opacity:.9;}
    .st-ch-lbl{margin-top:2px;font-size:12px;opacity:.6;text-align:center;}
    .st-ch-svg{width:100%;height:auto;display:block;overflow:visible;}
    .st-ch-line{stroke-dasharray:var(--len);stroke-dashoffset:calc(var(--len) * (1 - var(--p,0)));transition:stroke-dashoffset .12s linear;}
    .st-ch-area,.st-ch-dot{opacity:var(--p,0);transition:opacity .12s linear;}
    .st-ch-xlabels{display:flex;justify-content:space-between;margin-top:8px;font-size:12px;opacity:.6;}
    .st-ch-pie-wrap{display:flex;align-items:center;gap:28px;flex-wrap:wrap;justify-content:center;}
    .st-ch-pie{width:200px;height:200px;border-radius:9999px;flex:0 0 auto;-webkit-mask:radial-gradient(circle,transparent 52%,#000 53%);mask:radial-gradient(circle,transparent 52%,#000 53%);transform:scale(calc(.55 + .45 * var(--p,0))) rotate(calc((1 - var(--p,0)) * -28deg));opacity:var(--p,0);transition:transform .12s linear,opacity .12s linear;}
    .st-ch-legend{display:flex;flex-direction:column;gap:8px;}
    .st-ch-leg{display:flex;align-items:center;gap:8px;font-size:13px;}
    .st-ch-sw{width:12px;height:12px;border-radius:3px;flex:0 0 auto;}
    .st-form{display:flex;flex-direction:column;gap:14px;}
    .st-form-fields{display:flex;flex-direction:column;gap:14px;}
    .st-form-field{display:flex;flex-direction:column;gap:6px;}
    .st-form-field label{font-size:13px;font-weight:500;opacity:.9;}
    .st-form input,.st-form textarea{width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.14);border-radius:8px;padding:10px 12px;font-size:14px;color:inherit;outline:none;font-family:inherit;}
    .st-form input:focus,.st-form textarea:focus{border-color:rgba(255,255,255,0.4);}
    .st-form-btn{align-self:flex-start;margin-top:4px;color:#fff;font-weight:600;font-size:14px;padding:10px 22px;border-radius:9999px;border:0;cursor:pointer;}
    .st-form-btn[disabled]{opacity:.6;cursor:default;}
    .st-form-success{padding:16px 0;font-weight:500;}
    .st-form-error{color:#f87171;font-size:13px;}
    .st-si-card{position:sticky;}
    .st-si-card + .st-si-card{margin-top:18px;}
    .st-si-img{position:relative;width:100%;height:100vh;overflow:hidden;box-shadow:0 -10px 40px rgba(0,0,0,0.35);}
    .st-si-overlay{position:absolute;inset:0;padding:48px 28px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;background:linear-gradient(180deg,rgba(0,0,0,0.35) 0%,rgba(0,0,0,0.55) 100%);}
    .st-si-overlay>*{max-width:960px;}
    .st-si-overlay--h-left{align-items:flex-start;text-align:left;}
    .st-si-overlay--h-right{align-items:flex-end;text-align:right;}
    .st-si-overlay--v-top{justify-content:flex-start;}
    .st-si-overlay--v-bot{justify-content:flex-end;}
    .st-si-text{max-width:960px;padding:16px 20px;}
    .st-si-scrim{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0.35) 0%,rgba(0,0,0,0.55) 100%);pointer-events:none;}
    .st-si-txt{position:sticky;top:0;height:100vh;height:100svh;pointer-events:none;}
    .st-si-txt .st-si-text{pointer-events:auto;}
    .st-si-txt .st-si-overlay{position:relative;inset:auto;height:100%;background:none;}
    .st-pbuy__panel{display:flex;flex-direction:column;gap:20px;max-width:640px;}
    .st-pbuy--gal{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);gap:56px;align-items:start;}
    .st-pbuy--gal .st-pbuy__panel{max-width:none;}
    .st-pbuy__gal{position:sticky;top:24px;min-width:0;}
    .st-pbuy__view{position:relative;}
    .st-pbuy__track{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;border-radius:16px;background:#fff;}
    .st-pbuy__track::-webkit-scrollbar{display:none;}
    .st-pbuy__slide{flex:0 0 100%;aspect-ratio:1/1;scroll-snap-align:start;}
    .st-pbuy__slide img{width:100%;height:100%;object-fit:contain;display:block;}
    .st-pbuy__arrow{position:absolute;top:50%;transform:translateY(-50%);width:40px;height:40px;border-radius:50%;border:0;background:rgba(255,255,255,.92);color:#111;font-size:20px;line-height:1;cursor:pointer;box-shadow:0 2px 10px rgba(0,0,0,.12);}
    .st-pbuy__arrow--p{left:12px;}.st-pbuy__arrow--n{right:12px;}
    .st-pbuy__thumbs{display:flex;gap:10px;margin-top:12px;overflow-x:auto;scrollbar-width:none;}
    .st-pbuy__thumb{flex:0 0 72px;height:72px;padding:0;border:1.5px solid transparent;border-radius:10px;overflow:hidden;background:#fff;opacity:.6;cursor:pointer;}
    .st-pbuy__thumb img{width:100%;height:100%;object-fit:contain;display:block;}
    .st-pbuy__thumb.on{opacity:1;border-color:currentColor;}
    .st-pbuy__dots{display:none;justify-content:center;gap:6px;margin-top:12px;}
    .st-pbuy__dot{width:7px;height:7px;border-radius:50%;background:currentColor;opacity:.25;}
    .st-pbuy__dot.on{opacity:.85;}
    @media(max-width:860px){.st-pbuy--gal{grid-template-columns:minmax(0,1fr);gap:28px;}.st-pbuy__gal{position:relative;top:auto;}.st-pbuy__thumbs,.st-pbuy__arrow{display:none;}.st-pbuy__dots{display:flex;}}
    .st-pbuy__opt-label{font-size:13px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;margin-bottom:10px;opacity:.9;text-align:left;}
    .st-pbuy__swatches{display:flex;gap:10px;}
    .st-pbuy__swatch{position:relative;width:56px;height:20px;border:1px solid rgba(127,127,127,.35);padding:0;cursor:pointer;}
    .st-pbuy__swatch.on::after{content:'';position:absolute;left:25%;right:25%;bottom:-7px;height:2px;background:currentColor;}
    .st-pbuy__swatch.dead{opacity:.3;}
    .st-pbuy__row{display:flex;align-items:center;justify-content:space-between;gap:20px;border-top:1px solid rgba(127,127,127,.25);border-bottom:1px solid rgba(127,127,127,.25);padding:16px 0;}
    .st-pbuy__row-label{font-weight:500;}
    .st-pbuy__row-vals{display:flex;gap:22px;flex-wrap:wrap;justify-content:flex-end;}
    .st-pbuy__val{background:none;border:0;padding:2px 0 4px;font:inherit;font-weight:600;color:inherit;cursor:pointer;opacity:.7;border-bottom:1.5px solid transparent;}
    .st-pbuy__val.on{opacity:1;border-bottom-color:currentColor;}
    .st-pbuy__val.dead{opacity:.25;text-decoration:line-through;}
    .st-pbuy__cart.dead{opacity:.4;pointer-events:none;}
    .st-pbuy__qty{display:flex;align-items:center;gap:14px;}
    .st-pbuy__qty-b{width:34px;height:34px;border:1px solid rgba(127,127,127,.4);border-radius:8px;background:none;color:inherit;font:inherit;font-size:18px;cursor:pointer;}
    .st-pbuy__qty-b:hover{border-color:currentColor;}
    .st-pbuy__qty-v{min-width:2ch;text-align:center;font-weight:600;}
    .st-pgrid__bar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 14px;}
    .st-pgrid__count{font-size:14px;opacity:.7;}
    .st-pgrid__sort{background:transparent;border:0;color:inherit;font:inherit;font-size:14px;opacity:.85;cursor:pointer;}
    .st-pgrid__sort:hover,.st-pgrid__sort:focus{opacity:1;outline:none;}
    .st-pgrid__sort option{color:#111;}
    .st-pgrid__grid{display:grid;grid-template-columns:repeat(var(--pg-cols,4),1fr);gap:18px;}
    @media(max-width:900px){.st-pgrid__grid{grid-template-columns:repeat(var(--pg-cols-md,3),1fr);}}
    @media(max-width:640px){.st-pgrid__grid{grid-template-columns:repeat(var(--pg-cols-sm,2),1fr);}}
    .st-pgrid__t{display:block;min-width:0;text-decoration:none;color:inherit;}
    .st-pgrid__im{position:relative;display:block;aspect-ratio:1/1;overflow:hidden;border-radius:12px;background:rgba(127,127,127,0.12);}
    .st-pgrid__im img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;transition:transform .35s ease;}
    .st-pgrid__t:hover img{transform:scale(1.04);}
    .st-pgrid__n{display:block;margin-top:10px;font-size:15px;font-weight:600;line-height:1.35;}
    .st-pgrid__p{display:block;margin-top:2px;font-size:14px;opacity:.7;}
    .st-pgrid__more{height:1px;}
    .st-bento__all:hover{text-decoration:underline;}
    .st-bento__row{display:flex;align-items:stretch;}
    .st-bento--right .st-bento__row{flex-direction:row-reverse;}
    .st-bento__tile{position:relative;display:block;width:100%;aspect-ratio:1/1;border-radius:14px;overflow:hidden;text-decoration:none;color:inherit;}
    .st-bento__tile img{transition:transform .35s ease;}
    a.st-bento__tile:hover img{transform:scale(1.04);}
    .st-bento__row>.st-bento__tile{flex:1 1 0;min-width:0;}
    .st-bento__grid{flex:1 1 0;min-width:0;display:grid;grid-template-columns:1fr 1fr;align-content:start;}
    .st-bento__ovl{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:flex-end;padding:14px 16px;background:linear-gradient(180deg,rgba(0,0,0,0) 55%,rgba(0,0,0,0.55) 100%);}
    @media(hover:hover){.st-bento--cap-hover .st-bento__grid .st-bento__ovl{opacity:0;transition:opacity .25s ease;}.st-bento--cap-hover .st-bento__grid .st-bento__tile:hover .st-bento__ovl{opacity:1;}}
    .st-bento--cap-hidden .st-bento__grid .st-bento__ovl{display:none;}
    .st-cnt__row{display:flex;align-items:stretch;}
    .st-cnt__col{display:flex;flex-direction:column;gap:14px;min-width:0;}
    @media(max-width:640px){.st-cnt__row{flex-direction:column;}.st-cnt__col{flex:1 1 auto!important;}}
    @media(max-width:700px){.st-bento__row,.st-bento--right .st-bento__row{flex-direction:column;}}
    .st-si-txt+.st-si-card{margin-top:18px;}
    .st-scbg{position:absolute;top:0;bottom:0;left:calc(50% - 50vw);width:100vw;overflow:hidden;pointer-events:none;}
    .st-scbg-l{position:absolute;inset:-40px;background-size:cover;background-position:center;filter:blur(24px) saturate(1.1);transform:scale(1.08);opacity:0;transition:opacity .7s ease;}
    .st-scbg-l.on{opacity:.55;}
    .st-map{position:relative;}
    .st-map-sticky{position:sticky;top:0;height:100vh;height:100svh;display:flex;align-items:center;justify-content:center;overflow:hidden;}
    .st-map-frame{position:relative;width:min(1100px,92vw);aspect-ratio:16/10;max-height:72vh;border-radius:18px;border:1px solid rgba(255,255,255,0.12);overflow:hidden;background:#11131a;}
    .st-map-frame>.st-map-gl{position:absolute;inset:0;}
    .st-map-frame>.st-map-pin{position:absolute;left:50%;top:50%;}
    .st-map-pin{position:relative;width:22px;height:22px;opacity:0;z-index:2;transition:opacity .3s ease;pointer-events:none;}
    .st-map-pin.on{opacity:1;pointer-events:auto;}
    .st-map-dot{position:absolute;inset:0;border-radius:50%;border:3px solid #fff;box-sizing:border-box;}
    .st-map-pin.on .st-map-dot{animation:st-map-drop .65s cubic-bezier(.3,1.15,.42,1) both;}
    @keyframes st-map-drop{0%{transform:translateY(-52px) scale(.55);opacity:0;}50%{transform:translateY(0) scale(1.05);opacity:1;}66%{transform:translateY(-9px) scale(.98);}82%{transform:translateY(0) scale(1.02);}100%{transform:none;opacity:1;}}
    .st-map-pin.on .st-map-dot::before,.st-map-pin.on .st-map-dot::after{content:'';position:absolute;inset:-3px;border-radius:50%;border:2px solid rgba(255,255,255,.85);pointer-events:none;opacity:0;}
    .st-map-pin.on .st-map-dot::before{animation:st-map-ripple 1s ease-out .42s both;}
    .st-map-pin.on .st-map-dot::after{animation:st-map-ripple 1s ease-out .62s both;}
    @keyframes st-map-ripple{0%{transform:scale(1);opacity:.9;}100%{transform:scale(3);opacity:0;}}
    .st-map-card{position:absolute;bottom:32px;left:50%;width:max-content;max-width:240px;overflow:hidden;text-align:center;background:rgba(12,12,16,.92);border:1px solid rgba(255,255,255,.16);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.45);backdrop-filter:blur(6px);opacity:0;transform:translateX(-50%);transform-origin:50% 100%;}
    .st-map-pin.on .st-map-card{animation:st-map-card-pop .55s cubic-bezier(.3,1.5,.5,1) .5s both;}
    @keyframes st-map-card-pop{0%{opacity:0;transform:translateX(-50%) translateY(14px) scale(.6);}65%{opacity:1;transform:translateX(-50%) translateY(-4px) scale(1.04);}100%{opacity:1;transform:translateX(-50%) translateY(0) scale(1);}}
    .st-map-card img{display:block;width:100%;max-width:240px;min-width:160px;height:110px;object-fit:cover;}
    .st-map-card-t{color:#fff;font-size:14px;font-weight:700;line-height:1.25;padding:8px 12px 2px;}
    .st-map-card-t:last-child{padding-bottom:9px;}
    .st-map-card-d{color:rgba(255,255,255,.72);font-size:12px;line-height:1.45;padding:0 12px 9px;max-width:240px;white-space:normal;}
    .st-box{text-align:center;}
    .st-el__label{font-family:'Bebas Neue',system-ui,sans-serif;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#ff3366;}
    .st-el__content{margin-top:8px;font-size:15px;line-height:1.5;opacity:0.92;}
    .st-empty{text-align:center;opacity:0.4;padding:64px 0;}
    .st-hero{position:relative;width:100vw;margin-left:calc(50% - 50vw);}
    .st-hero--autoplay{height:100vh;}
    .st-hero__sticky{position:sticky;top:0;height:100vh;width:100%;overflow:hidden;}
    .st-hero video,.st-hero__canvas,.st-hero__bgimg{width:100%;height:100%;object-fit:cover;object-position:var(--st-hero-focus,50%) 50%;display:block;}
    .st-hero__pp{position:absolute;bottom:16px;left:16px;z-index:6;width:44px;height:44px;border-radius:9999px;background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.25);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px);}
    .st-hero__pp:hover{background:rgba(0,0,0,.65);}
    .st-hero__pp svg{width:20px;height:20px;fill:currentColor;}
    .st-hero__overlay{container-type:inline-size;position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;text-align:center;padding:32px;pointer-events:none;background:radial-gradient(ellipse at center, rgba(0,0,0,0.5), rgba(0,0,0,0.22) 50%, rgba(0,0,0,0) 78%);}
    .st-hero__overlay h1,.st-hero__overlay h2{overflow-wrap:break-word;max-width:100%;font-family:'Bebas Neue',system-ui,sans-serif;color:#ffffff;font-weight:700;line-height:1.05;font-size:64px;max-width:min(768px,100%);text-shadow:0 2px 30px rgba(0,0,0,0.55);}
    .st-hero__overlay p{overflow-wrap:break-word;font-family:'Inter',system-ui,sans-serif;color:#ffffff;font-size:32px;line-height:1.4;max-width:736px;opacity:0.93;text-shadow:0 1px 16px rgba(0,0,0,0.5);white-space:pre-line;}
    .st-hero__img{max-width:min(72%,416px);max-height:38vh;object-fit:contain;}
    @media (max-width:640px){.st-hero__overlay h1,.st-hero__overlay h2{font-size:44px;}.st-hero__overlay p{font-size:20px;}.st-hero__img{max-height:30vh;}}
    @media (max-width:640px){h1.st-h-fluid{font-size:40px !important;}h2.st-h-fluid{font-size:32px !important;}}
    .st-hero__overlay--corner{padding:32px;}
    .st-hero__overlay--tl,.st-hero__overlay--tr{justify-content:flex-start;justify-content:safe flex-start;}
    .st-hero__overlay--bl,.st-hero__overlay--br{justify-content:flex-end;justify-content:safe flex-end;}
    .st-hero__overlay--tl,.st-hero__overlay--bl{align-items:flex-start;align-items:safe flex-start;text-align:left;}
    .st-hero__overlay--tr,.st-hero__overlay--br{align-items:flex-end;align-items:safe flex-end;text-align:right;}
    .st-hero__overlay--corner{background:none;}
    .st-hero__awayhold{position:absolute;top:0;left:0;right:0;height:900vh;z-index:2;pointer-events:none;}
    .st-hero__overlay--away{position:sticky;top:0;right:auto;bottom:auto;left:auto;height:100vh;max-height:100vh;}
    @supports (animation-timeline:view()){
      .st-hero__awayhold{view-timeline-name:--st-hero-away;view-timeline-axis:block;}
      .st-hero__overlay--away{animation-name:st-hero-away;animation-timing-function:linear;animation-fill-mode:both;animation-timeline:--st-hero-away;animation-range:contain 0% contain 100%;}
      @keyframes st-hero-away{0%{transform:translateY(0);opacity:1;--st-hero-pe:auto;}78%{opacity:1;}94%{--st-hero-pe:auto;}95%{opacity:0;--st-hero-pe:none;}100%{transform:translateY(-85%);opacity:0;--st-hero-pe:none;}}
      .st-hero--txtin{view-timeline-name:--st-hero-in;view-timeline-axis:block;}
      .st-hero__overlay--in{animation-name:st-hero-in;animation-timing-function:ease-out;animation-fill-mode:both;animation-timeline:--st-hero-in;animation-range:contain 0% contain 100%;}
      @keyframes st-hero-in{0%{transform:translateY(110%);opacity:0;--st-hero-pe:none;}88%{transform:translateY(110%);opacity:0;--st-hero-pe:none;}91%{--st-hero-pe:none;}92%{--st-hero-pe:auto;}96%{transform:translateY(0);opacity:1;}100%{transform:translateY(0);opacity:1;--st-hero-pe:auto;}}
    }
    .st-hero--autoplay.st-hero--away{height:1000vh;}
    .st-hero--autoplay.st-hero--away>.st-hero__sticky{height:100vh;position:sticky;top:0;}
    .st-hero--autoplay.st-hero--txtin{height:200vh;}
    .st-hero--autoplay.st-hero--txtin>.st-hero__sticky{height:100vh;position:sticky;top:0;}
    .st-hero--pos-bl .st-hero__pp{left:auto;right:16px;}
    .st-hero__cta{display:inline-block;background:#ff3366;color:#fff;font-weight:600;padding:11px 26px;border-radius:9999px;text-decoration:none;box-shadow:0 4px 24px rgba(0,0,0,0.35);pointer-events:var(--st-hero-pe,auto);cursor:pointer;}
    .st-header{position:relative;display:flex;align-items:center;justify-content:space-between;gap:24px;padding:14px 24px;z-index:50;}
    .st-header--sticky{position:sticky;top:0;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);}
    .st-header--clear{backdrop-filter:none;-webkit-backdrop-filter:none;}
    .st-header__logo{flex-shrink:0;font-weight:700;font-size:17px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;white-space:nowrap;}
    .st-header__logo img{height:28px;width:auto;display:block;}
    .st-header__nav{display:flex;align-items:center;gap:20px;flex-wrap:nowrap;flex-shrink:0;justify-content:flex-end;white-space:nowrap;}
    .st-header__util{font-size:14px;font-weight:600;color:inherit;opacity:.85;text-decoration:none;white-space:nowrap;}.st-header__util:hover{opacity:1;}
    .st-header__util--ic{display:inline-flex;align-items:center;}.st-header__util--ic svg{width:17px;height:17px;display:block;}
    .st-header__right{display:flex;align-items:center;gap:20px;flex-shrink:0;}
    .st-header--nav-center{justify-content:flex-start;}.st-header--nav-center .st-header__logo{flex:1 1 0;}.st-header--nav-center .st-header__nav{flex:0 1 auto;justify-content:center;}.st-header--nav-center .st-header__right{flex:1 1 0;justify-content:flex-end;}
    .st-header--logo-center{justify-content:flex-start;}.st-header--logo-center .st-header__nav{order:1;flex:1 1 0;justify-content:flex-start;}.st-header--logo-center .st-header__logo{order:2;}.st-header--logo-center .st-header__right{order:3;flex:1 1 0;justify-content:flex-end;}.st-header--logo-center .st-header__burger{order:9;}
    .st-header--left-group{justify-content:flex-start;}.st-header--left-group .st-header__nav{flex:1 1 auto;justify-content:flex-start;}.st-header--left-group .st-header__right{margin-left:auto;}
    .st-header--center-all{justify-content:center;}
    .st-header__nav a{text-decoration:none;font-size:14px;opacity:.85;transition:opacity .15s;}
    .st-header__nav a:hover{opacity:1;}
    .st-header__item{position:relative;display:flex;align-items:center;}
    .st-header__plink{display:inline-flex;align-items:center;gap:5px;text-decoration:none;font-size:14px;opacity:.85;transition:opacity .15s;cursor:pointer;white-space:nowrap;}
    .st-header__plink:hover{opacity:1;}
    .st-header__caret{width:14px;height:14px;opacity:.7;flex-shrink:0;}
    .st-header__sub{display:none;position:absolute;top:calc(100% + 8px);left:50%;transform:translateX(-50%);min-width:max-content;flex-direction:column;align-items:flex-start;gap:1.6px;padding:9px 15px;border-radius:12px;background:var(--hdr-sub-bg,rgba(12,12,16,.92));box-shadow:0 16px 34px rgba(0,0,0,0.28);z-index:5;}
    .st-header__sub::before{content:'';position:absolute;left:0;right:0;top:-9px;height:9px;}
    .st-header__item:hover .st-header__sub,.st-header__item:focus-within .st-header__sub{display:flex;}
    .st-header__sub a{font-size:14px;padding:3.52px 0;}
    .st-header__burger{display:none;flex-shrink:0;align-items:center;justify-content:center;width:40px;height:40px;padding:0;border:0;border-radius:8px;background:transparent;color:inherit;cursor:pointer;}
    .st-header__burger:hover{background:rgba(127,127,127,0.18);}
    .st-header__burger svg{width:24px;height:24px;display:block;}
    .st-header.is-nav-collapsed .st-header__burger{display:inline-flex;}
    .st-header.is-nav-collapsed .st-header__nav{display:none;position:absolute;top:100%;left:12px;right:12px;flex-direction:column;align-items:stretch;gap:2.4px;padding:12px 15px;border-radius:0 0 14px 14px;background:inherit;box-shadow:0 16px 34px rgba(0,0,0,0.28);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);white-space:normal;}
    .st-header.is-nav-collapsed.is-menu-open .st-header__nav{display:flex;}
    .st-header.is-nav-collapsed .st-header__nav a{font-size:15px;opacity:1;padding:8px 4px;}
    .st-header.is-nav-collapsed .st-header__item{position:static;flex-direction:column;align-items:stretch;}
    .st-header.is-nav-collapsed .st-header__plink{font-size:15px;opacity:1;padding:8px 4px;}
    .st-header.is-nav-collapsed .st-header__sub{display:flex;position:static;transform:none;min-width:0;background:transparent;box-shadow:none;padding:0 0 6px 15px;}
    .st-header.is-nav-collapsed .st-header__sub::before{display:none;}
    .st-header.is-nav-collapsed .st-header__sub a{font-size:14px;opacity:.85;padding:5px 4px;}
    .st-header.is-nav-collapsed .st-header__cta{margin-top:7px;text-align:center;}
    .st-header__cta{display:inline-block;background:#ff3366;color:#fff;opacity:1 !important;font-weight:600;font-size:14px;padding:8px 18px;border-radius:9999px;text-decoration:none;}
    .st-gh{position:fixed;top:0;left:0;right:0;z-index:60;transition:transform .4s cubic-bezier(.4,0,.2,1);}
    .st-gh.is-hidden{transform:translateY(-115%);}
    .st-gh__inner{display:flex;align-items:center;justify-content:space-between;gap:20px;margin:14px auto;max-width:1000px;padding:10px 18px 10px 22px;border-radius:9999px;color:var(--gh-fg);background:var(--gh-glass);border:1px solid var(--gh-border);box-shadow:0 10px 40px rgba(0,0,0,.16);backdrop-filter:blur(16px) saturate(1.3);-webkit-backdrop-filter:blur(16px) saturate(1.3);transition:margin .4s ease,max-width .4s ease,border-radius .4s ease,background .4s ease,box-shadow .4s ease;}
    .st-gh.is-scrolled .st-gh__inner{margin:8px auto;max-width:1000px;border-radius:14px;background:var(--gh-solid);border-color:transparent;border-bottom:1px solid var(--gh-line);box-shadow:0 2px 24px rgba(0,0,0,.12);backdrop-filter:none;-webkit-backdrop-filter:none;}
    .st-gh__logo{flex-shrink:0;font-weight:700;font-size:17px;text-decoration:none;color:inherit;display:inline-flex;align-items:center;gap:8px;white-space:nowrap;}
    .st-gh__logo img{height:26px;width:auto;display:block;}
    .st-gh__nav{display:flex;align-items:center;gap:24px;}
    .st-gh__link{text-decoration:none;color:inherit;font-size:14px;font-weight:500;opacity:.62;position:relative;padding:3.2px 0;white-space:nowrap;transition:opacity .2s;}
    .st-gh__link:hover{opacity:.9;}
    .st-gh__link.is-active{opacity:1;}
    .st-gh__link.is-active::after{content:'';position:absolute;left:0;right:0;bottom:-3px;height:2px;border-radius:2px;background:var(--gh-accent);}
    .st-gh__item{position:relative;display:flex;align-items:center;}
    .st-gh__plink{display:inline-flex;align-items:center;gap:4px;cursor:pointer;}
    .st-gh__caret{width:13px;height:13px;opacity:.7;flex-shrink:0;}
    .st-gh__sub{display:none;position:absolute;top:calc(100% + 8px);left:50%;transform:translateX(-50%);min-width:max-content;flex-direction:column;align-items:flex-start;gap:0.8px;padding:9px 14px;border-radius:14px;background:var(--gh-solid);border:1px solid var(--gh-line);box-shadow:0 18px 40px rgba(0,0,0,.3);z-index:5;}
    .st-gh__sub::before{content:'';position:absolute;left:0;right:0;top:-10px;height:10px;}
    .st-gh__item:hover .st-gh__sub,.st-gh__item:focus-within .st-gh__sub{display:flex;}
    .st-gh__sub .st-gh__link{font-size:13px;padding:4px 0;}
    .st-gh__cta{flex-shrink:0;background:var(--gh-accent);color:#fff;font-weight:600;font-size:14px;padding:9px 19px;border-radius:9999px;text-decoration:none;white-space:nowrap;}
    .st-gh__util{flex-shrink:0;color:inherit;font-size:14px;font-weight:600;opacity:.75;text-decoration:none;white-space:nowrap;}.st-gh__util:hover{opacity:1;}
    .st-gh__util--ic{display:inline-flex;align-items:center;}.st-gh__util--ic svg{width:16px;height:16px;display:block;}
    .st-gh__right{display:flex;align-items:center;gap:20px;flex-shrink:0;}
    .st-gh--nav-center .st-gh__inner{justify-content:flex-start;}.st-gh--nav-center .st-gh__logo{flex:1 1 0;}.st-gh--nav-center .st-gh__nav{flex:0 1 auto;}.st-gh--nav-center .st-gh__right{flex:1 1 0;justify-content:flex-end;}
    .st-gh--logo-center .st-gh__inner{justify-content:flex-start;}.st-gh--logo-center .st-gh__nav{order:1;flex:1 1 0;}.st-gh--logo-center .st-gh__logo{order:2;}.st-gh--logo-center .st-gh__right{order:3;flex:1 1 0;justify-content:flex-end;}.st-gh--logo-center .st-gh__burger{order:9;}
    .st-gh--left-group .st-gh__inner{justify-content:flex-start;}.st-gh--left-group .st-gh__nav{flex:1 1 auto;justify-content:flex-start;}.st-gh--left-group .st-gh__right{margin-left:auto;}
    .st-gh--center-all .st-gh__inner{justify-content:center;}
    .st-gh--navmenu .st-gh__inner{position:relative;}
    .st-gh--navmenu .st-gh__burger{display:inline-flex;}
    .st-gh--navmenu .st-gh__nav{display:none;position:absolute;top:calc(100% + 10px);left:0;right:0;flex-direction:column;align-items:stretch;gap:1.6px;padding:10px 14px;border-radius:16px;background:var(--gh-solid);border:1px solid var(--gh-line);box-shadow:0 18px 40px rgba(0,0,0,.3);}
    .st-gh--navmenu.is-menu-open .st-gh__nav{display:flex;}
    .st-gh--navmenu .st-gh__nav .st-gh__link{font-size:16px;opacity:.9;padding:10px 6px;}
    .st-gh--navmenu .st-gh__nav .st-gh__link.is-active::after{display:none;}
    .st-gh--navmenu .st-gh__item{position:static;flex-direction:column;align-items:stretch;}
    .st-gh--navmenu .st-gh__sub{display:flex;position:static;transform:none;min-width:0;background:transparent;border:0;box-shadow:none;padding:0 0 5px 14px;}
    .st-gh--navmenu .st-gh__sub::before{display:none;}
    .st-gh--navmenu .st-gh__sub .st-gh__link{font-size:14px;opacity:.75;padding:6px 6px;}
    .st-gh__burger{display:none;flex-shrink:0;align-items:center;justify-content:center;width:40px;height:40px;margin-left:2.4px;padding:0;border:0;border-radius:9999px;background:transparent;color:inherit;cursor:pointer;}
    .st-gh__burger:hover{background:rgba(127,127,127,0.18);}
    .st-gh__burger svg{width:24px;height:24px;display:block;}
    @media (max-width:760px){
      .st-gh__inner{margin:10px;position:relative;}
      .st-gh__burger{display:inline-flex;}
      .st-gh__nav{display:none;position:absolute;top:calc(100% + 10px);left:0;right:0;flex-direction:column;align-items:stretch;gap:1.6px;padding:10px 14px;border-radius:16px;background:var(--gh-solid);border:1px solid var(--gh-line);box-shadow:0 18px 40px rgba(0,0,0,.3);}
      .st-gh.is-menu-open .st-gh__nav{display:flex;}
      .st-gh__nav .st-gh__link{font-size:16px;opacity:.9;padding:10px 6px;}
      .st-gh__nav .st-gh__link.is-active::after{display:none;}
      .st-gh__item{position:static;flex-direction:column;align-items:stretch;}
      .st-gh__sub{display:flex;position:static;transform:none;min-width:0;background:transparent;border:0;box-shadow:none;padding:0 0 5px 14px;}
      .st-gh__sub::before{display:none;}
      .st-gh__sub .st-gh__link{font-size:14px;opacity:.75;padding:6px 6px;}
    }
    .st-mh{z-index:50;}
    .st-mh.is-open{z-index:2147483000;}
    .st-mh__bar{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:18px 26px;color:var(--mh-fg);}
    .st-mh__util{font-size:14px;font-weight:600;color:var(--mh-fg);opacity:.85;text-decoration:none;white-space:nowrap;}.st-mh__util:hover{opacity:1;}
    .st-mh__util--ic{display:inline-flex;align-items:center;}.st-mh__util--ic svg{width:17px;height:17px;display:block;}
    .st-mh__lang{position:relative;display:inline-flex;}
    .st-mh__lang-btn{display:inline-flex;align-items:center;gap:5px;background:none;border:0;cursor:pointer;padding:0;}
    .st-mh__lang-btn svg{width:16px;height:16px;display:block;}
    .st-mh__lang-menu{position:absolute;top:100%;right:0;z-index:60;display:none;min-width:112px;padding:6px 0;border-radius:10px;background:var(--mh-bg);box-shadow:0 12px 32px rgba(0,0,0,.35);}
    .st-mh__lang:hover .st-mh__lang-menu,.st-mh__lang:focus-within .st-mh__lang-menu{display:block;}
    .st-mh__lang-item{display:block;padding:6px 14px;font-size:14px;font-weight:600;color:var(--mh-fg);opacity:.85;text-decoration:none;white-space:nowrap;}
    .st-mh__lang-item:hover{opacity:1;}
    .st-mh__lang-item.is-cur{opacity:1;color:var(--mh-accent);}
    .st-mh__logo{flex-shrink:0;font-weight:700;font-size:17px;letter-spacing:-.01em;text-decoration:none;color:inherit;display:inline-flex;align-items:center;gap:8px;white-space:nowrap;}
    .st-mh__logo img{height:26px;width:auto;display:block;}
    .st-mh__right{display:flex;align-items:center;gap:16px;}
    .st-mh__cta{flex-shrink:0;background:var(--mh-accent);color:var(--mh-cta-fg);font-weight:600;font-size:14px;padding:8px 18px;border-radius:9999px;text-decoration:none;white-space:nowrap;}
    .st-mh__burger{display:flex;flex-direction:column;justify-content:center;gap:6px;width:32px;height:32px;padding:0 2px;border:0;background:transparent;color:inherit;cursor:pointer;}
    .st-mh__burger span{display:block;width:100%;height:1.5px;border-radius:2px;background:currentColor;transition:transform .3s ease;}
    .st-mh__burger:hover span:first-child{transform:translateY(-2px);}
    .st-mh__burger:hover span:last-child{transform:translateY(2px);}
    .st-mh__panel{position:fixed;inset:0;display:flex;flex-direction:column;background:var(--mh-bg);color:var(--mh-fg);opacity:0;visibility:hidden;transition:opacity .45s cubic-bezier(.4,0,.2,1),visibility 0s .45s;}
    .st-mh.is-open .st-mh__panel{opacity:1;visibility:visible;transition:opacity .45s cubic-bezier(.4,0,.2,1),visibility 0s;}
    .st-mh__panel-bar{display:flex;align-items:center;justify-content:space-between;gap:24px;flex-shrink:0;padding:18px 26px;}
    .st-mh__close{display:flex;align-items:center;justify-content:center;width:38px;height:38px;padding:0;border:0;border-radius:9999px;background:transparent;color:inherit;cursor:pointer;transition:background .2s;}
    .st-mh__close:hover{background:color-mix(in srgb,var(--mh-fg) 12%,transparent);}
    .st-mh__close svg{width:24px;height:24px;display:block;}
    .st-mh__body{flex:1;min-height:0;overflow-y:auto;display:flex;align-items:flex-start;gap:clamp(32px,6vw,80px);width:100%;max-width:1200px;margin:0 auto;padding:2vh 26px 4vh;scrollbar-width:none;}
    .st-mh__body::-webkit-scrollbar{width:0;height:0;display:none;}
    .st-mh__items,.st-mh__media{margin-top:auto;margin-bottom:auto;}
    .st-mh__items{flex:1;min-width:0;display:flex;flex-direction:column;align-items:flex-start;gap:.12em;}
    .st-mh__item{display:block;text-decoration:none;color:var(--mh-fg);color:color-mix(in srgb,var(--mh-fg) 55%,transparent);font-size:clamp(27px,4.2vw,53px);line-height:1.14;font-weight:600;letter-spacing:-.03em;opacity:0;transition:color .3s ease;}
    .st-mh__item span{display:inline-block;transition:transform .35s cubic-bezier(.4,0,.2,1);}
    .st-mh__item:hover,.st-mh__item:focus-visible{color:var(--mh-fg);}
    .st-mh__item:hover span{transform:translateX(14px);}
    .st-mh.is-open .st-mh__item{animation:st-mh-in .6s cubic-bezier(.22,.7,.3,1) forwards;animation-delay:calc(var(--i) * 55ms + 130ms);}
    .st-mh__subs{display:flex;flex-wrap:wrap;column-gap:22px;row-gap:2.4px;margin:1.6px 0 8px 3px;opacity:0;}
    .st-mh.is-open .st-mh__subs{animation:st-mh-in .6s cubic-bezier(.22,.7,.3,1) forwards;animation-delay:calc(var(--i) * 55ms + 130ms);}
    .st-mh__subitem{text-decoration:none;color:var(--mh-fg);color:color-mix(in srgb,var(--mh-fg) 60%,transparent);font-size:clamp(15px,1.6vw,18px);font-weight:500;line-height:1.5;transition:color .3s ease;}
    .st-mh__subitem:hover,.st-mh__subitem:focus-visible{color:var(--mh-fg);}
    @keyframes st-mh-in{from{opacity:0;transform:translateY(26px);}to{opacity:1;transform:none;}}
    .st-mh__cta--menu{margin-top:29px;font-size:15px;padding:12px 27px;opacity:0;}
    .st-mh.is-open .st-mh__cta--menu{animation:st-mh-in .6s cubic-bezier(.22,.7,.3,1) forwards;animation-delay:calc(var(--i) * 55ms + 130ms);}
    .st-mh__media{position:relative;flex-shrink:0;width:min(38%,400px);aspect-ratio:3/4;border-radius:14px;overflow:hidden;background:color-mix(in srgb,var(--mh-fg) 7%,transparent);opacity:0;transition:opacity .5s ease .2s;}
    .st-mh.is-open .st-mh__media{opacity:1;}
    .st-mh__shot{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transform:scale(1.04);transition:opacity .5s ease,transform 1.1s ease;}
    .st-mh__shot.is-on{opacity:1;transform:none;}
    @media (max-width:820px){
      .st-mh__media{display:none;}
      .st-mh__body{padding:0 22px 6vh;}
      .st-mh__item{font-size:clamp(25px,7vw,38px);}
      .st-mh__item:hover span{transform:none;}
    }
    @media (max-width:520px){.st-mh__cta--bar{display:none;}}
    @media (prefers-reduced-motion:reduce){
      .st-mh.is-open .st-mh__item,.st-mh.is-open .st-mh__cta--menu{animation:none;opacity:1;}
      .st-mh__panel,.st-mh__media,.st-mh__shot,.st-mh__item span{transition:none;}
    }
    .st-footer{padding:40px 24px;text-align:center;font-size:14px;display:flex;flex-direction:column;gap:12px;align-items:center;}
    .st-footer__links{display:flex;gap:20px;flex-wrap:wrap;justify-content:center;}
    .st-footer__links a{text-decoration:none;opacity:.85;transition:opacity .15s;}
    .st-footer__links a:hover{opacity:1;}
    .st-anchor{display:block;height:0;scroll-margin-top:84px;}
    #st-consent{position:fixed;z-index:2147483600;left:0;right:0;bottom:0;display:flex;justify-content:center;padding:16px;pointer-events:none;font-family:'Inter',system-ui,-apple-system,sans-serif;}
    #st-consent.st-consent--left{justify-content:flex-start;}
    #st-consent.st-consent--right{justify-content:flex-end;}
    #st-consent .st-consent__inner{pointer-events:auto;display:flex;align-items:center;gap:16px 20px;flex-wrap:wrap;width:100%;max-width:1100px;padding:16px 20px;border-radius:14px;box-shadow:0 16px 50px rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.12);opacity:0;transform:translateY(20px);transition:opacity .3s ease,transform .3s ease;}
    #st-consent.st-consent--left .st-consent__inner,#st-consent.st-consent--right .st-consent__inner{max-width:400px;flex-direction:column;align-items:stretch;}
    #st-consent.st-consent--in .st-consent__inner{opacity:1;transform:none;}
    #st-consent.st-consent--out .st-consent__inner{opacity:0;transform:translateY(20px);}
    #st-consent .st-consent__msg{margin:0;flex:1 1 280px;min-width:200px;font-size:13px;line-height:1.5;opacity:.92;}
    #st-consent .st-consent__link{text-decoration:underline;white-space:nowrap;}
    #st-consent .st-consent__actions{display:flex;gap:10px;flex-shrink:0;justify-content:flex-end;}
    #st-consent .st-consent__btn{font:inherit;font-size:13px;font-weight:600;padding:9px 18px;border-radius:9999px;border:0;cursor:pointer;white-space:nowrap;}
    #st-consent .st-consent__decline{background:transparent;border:1px solid currentColor;color:inherit;opacity:.85;}
    #st-consent .st-consent__decline:hover{opacity:1;}
    @media (max-width:560px){#st-consent .st-consent__inner{flex-direction:column;align-items:stretch;}#st-consent .st-consent__actions{justify-content:stretch;}#st-consent .st-consent__btn{flex:1;}}
    [data-st-reveal]{opacity:0;transform:translateY(28px);transition:opacity .7s ease,transform .7s ease;}
    [data-st-reveal].st-in{opacity:1;transform:none;}
    @media (prefers-reduced-motion:reduce){[data-st-reveal]{transition:none;transform:none;opacity:1;}}
  </style>

<meta name="robots" content="index,follow,max-image-preview:large">
<meta name="keywords" content="SilverQueen, SQB Engine, barangay, Philippines, social trade, barangay marketplace, last-mile delivery, SilverSuite, GoldSuite, PlatinumSuite">
<meta name="description" content="SilverQueen Engines give barangay sellers more space, more reach, and a local last-mile route. Choose SilverSuite, GoldSuite, or PlatinumSuite.">
<meta property="og:title" content="SilverQueen: Your Engine in Every Barangay">
<meta property="og:description" content="SilverQueen Engines give barangay sellers more space, more reach, and a local last-mile route. Choose SilverSuite, GoldSuite, or PlatinumSuite.">
<meta property="og:site_name" content="SilverQueen"><meta property="og:image" content="https://silverqueen.pro/assets/silverqueen/images/archipelago.jpeg"><meta property="og:image:alt" content="The Philippine archipelago illuminated by community lights"><meta property="og:type" content="website"><meta property="og:url" content="https://silverqueen.pro/sqb-engine-v2">
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:image" content="https://silverqueen.pro/assets/silverqueen/images/archipelago.jpeg"><meta name="twitter:title" content="SilverQueen: Your Engine in Every Barangay"><meta name="twitter:description" content="SilverQueen Engines give barangay sellers more space, more reach, and a local last-mile route. Choose SilverSuite, GoldSuite, or PlatinumSuite.">
<link rel="canonical" href="https://silverqueen.pro/sqb-engine-v2">
<link rel="preload" as="image" href="/assets/silverqueen/sqb-engine-v2/assets/frames/frame_0001.webp" type="image/webp" fetchpriority="high">
<style>:root{--display:'Bebas Neue',system-ui,sans-serif;--body:'Inter',Arial,sans-serif;--cyan:#00E5FF;--gold:#FFC83D;--pink:#FF2E93;--ink:#050A10}.st-header{background:rgba(5,10,16,.92)!important;color:#fff!important;border-bottom:1px solid rgba(255,255,255,.08);z-index:40}.st-header__logo{color:#fff!important}.st-header__nav a{color:#dce9ef!important}.st-header__cta{background:#FFC83D!important;color:#050A10!important}.stx-closing-statement__cta{background:#FFC83D!important;color:#050A10!important;box-shadow:0 4px 20px rgba(255,200,61,.2)!important}.stx-closing-statement__cta:hover{background:#ffe08a!important;color:#050A10!important}.st-header__burger{color:#fff}.v2-header-tagline{color:#9aaeb9;font-size:10px;letter-spacing:.16em;font-weight:800;white-space:nowrap;margin-left:auto;margin-right:22px}.v2-wrap{background:#050A10;color:#eff8fc;font-family:Inter,Arial,sans-serif}.v2-wrap *{box-sizing:border-box}.v2-section{position:relative;min-height:72vh;padding:clamp(64px,10vw,140px) max(6vw,22px);display:flex;align-items:center;justify-content:center;scroll-margin-top:72px}.v2-inner{width:min(1160px,100%);margin:auto}.v2-kicker{font-size:12px;letter-spacing:.19em;color:#00E5FF;font-weight:800;text-transform:uppercase;margin:0 0 18px}.v2-section h2{font-family:'Bebas Neue',Impact,sans-serif;font-size:clamp(42px,7vw,88px);line-height:.98;letter-spacing:.01em;margin:0 0 22px;color:#fff}.v2-section h3{font-size:clamp(22px,3vw,32px);margin:8px 0 12px}.v2-lede,.v2-copy{font-size:clamp(17px,1.7vw,21px);line-height:1.65;color:#b8c8d2;max-width:780px}.v2-hero{min-height:100vh;padding:0}.v2-hero .st-hero__sticky{height:100svh}.v2-hero .st-hero__overlay{padding:22px;pointer-events:auto;background:linear-gradient(0deg,rgba(5,10,16,.62),rgba(5,10,16,.08) 80%)}.v2-hero .st-hero__overlay h1{font-size:clamp(48px,9vw,110px);max-width:1000px;line-height:.95}.v2-hero .st-hero__overlay p{font-size:clamp(18px,2vw,26px);max-width:760px}.v2-label{color:#FFC83D;text-transform:uppercase;letter-spacing:.15em;font-weight:800;font-size:12px}.v2-btn{display:inline-flex;align-items:center;justify-content:center;min-height:50px;padding:0 23px;border-radius:999px;background:#FFC83D;color:#071019;text-decoration:none;font-weight:800;margin:10px 8px 0 0}.v2-btn--outline{background:transparent;color:#fff;border:1px solid rgba(255,255,255,.28)}.v2-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:30px}.v2-card{padding:26px;border:1px solid rgba(0,229,255,.19);border-radius:18px;background:linear-gradient(145deg,rgba(12,31,42,.9),rgba(8,14,23,.96));box-shadow:0 0 30px rgba(0,229,255,.04)}.v2-card p{color:#bac8d0;line-height:1.6}.v2-card--featured{border-color:#FFC83D;box-shadow:0 0 38px rgba(255,200,61,.11);transform:translateY(-8px)}.v2-tag{font-size:12px;color:#00E5FF;letter-spacing:.12em;font-weight:800}.v2-price{font-size:40px;font-weight:850;color:#FFC83D;margin:10px 0}.v2-list{padding-left:18px;color:#cad7de;line-height:1.9}.v2-steps{display:flex;gap:12px;flex-wrap:wrap;margin:28px 0}.v2-steps span{padding:12px 15px;border:1px solid rgba(0,229,255,.27);border-radius:999px;color:#d8faff}.v2-disclaimer{font-size:13px!important;color:#8d9ca4!important}.v2-auth{background:radial-gradient(ellipse at center,rgba(0,229,255,.07),transparent 65%)}.v2-auth .auth-content{width:min(860px,100%);margin:auto}.sq-disclosure-footer{display:block!important;width:100%;margin:0;padding:56px max(6vw,22px) 28px;background:#071019!important;color:#a7b5bd!important;border-top:1px solid rgba(0,229,255,.2);font:15px/1.7 Inter,Arial,sans-serif}.sq-disclosure-inner{width:min(980px,100%);margin:0 auto;display:flex;flex-direction:column;align-items:flex-start;gap:12px}.sq-disclosure-inner p{margin:0;color:#a7b5bd}.sq-disclosure-footer a{color:#00E5FF;text-decoration:none}.sq-disclosure-footer a:hover{text-decoration:underline}.sq-disclosure-footer .footer-brand{font:700 30px/1 'Bebas Neue',Impact,sans-serif;color:#fff;letter-spacing:.03em;margin-bottom:8px}.sq-disclosure-links{display:flex;flex-wrap:wrap;gap:10px;align-items:center}.sq-disclosure-footer img{display:block;width:112px;max-width:100%;height:auto;margin-top:12px}.sq-disclosure-footer .footer-end{font-size:12px;color:#6f838e;margin-top:8px}.v2-rollout-rows{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:32px 0}.v2-rollout-rows div{padding:16px;border-left:2px solid #00E5FF;background:rgba(255,255,255,.03)}.v2-rollout-rows b{color:#FFC83D;display:block;margin-bottom:6px}.v2-engine{border-color:rgba(255,46,147,.4)}.v2-status{color:#FF2E93;font-size:12px;letter-spacing:.12em;font-weight:800}.v2-map-note{font-size:12px;color:#83949d;margin-top:18px}.v2-footer-line{color:#00E5FF}.v2-hero-cta{pointer-events:auto}.v2-hero .st-hero__overlay>*{pointer-events:auto}@media(prefers-reduced-motion:reduce){.v2-hero{height:100svh!important}}@media(max-width:760px){.v2-header-tagline{display:none}.v2-grid{grid-template-columns:1fr}.v2-card--featured{transform:none}.v2-rollout-rows{grid-template-columns:1fr 1fr}.v2-section{min-height:auto;padding:76px 22px}.v2-hero{min-height:100vh;padding:0}.v2-hero .st-hero__sticky{height:60svh;top:0}.v2-hero .st-hero__overlay{height:60svh}.v2-auth .auth-content{padding:12px}.v2-hero .st-hero__overlay h1{font-size:clamp(44px,12vw,68px)}}@media(max-width:380px){.v2-rollout-rows{grid-template-columns:1fr}.v2-btn{width:100%;margin-right:0}}</style>
<style>
body{background:#050A10!important;color:#f5f8fb!important}.v2-wrap{background:#050A10;color:#eff8fc;font-family:Inter,Arial,sans-serif}.st-header{background:rgba(5,10,16,.94)!important;color:#fff!important;z-index:50}.st-header__logo{color:#fff!important}.st-header__nav a{color:#e4edf2!important}.st-header__cta{background:#FFC83D!important;color:#050A10!important}.stx-closing-statement__cta{background:#FFC83D!important;color:#050A10!important;box-shadow:0 4px 20px rgba(255,200,61,.2)!important}.stx-closing-statement__cta:hover{background:#ffe08a!important;color:#050A10!important}.st-header__burger{color:#fff!important}.sqb-header-tagline{color:#9aadb7;font:800 10px/1 Inter,Arial,sans-serif;letter-spacing:.16em;white-space:nowrap;margin-left:auto;margin-right:20px}.sqb-hero-kicker,.sqb-overline{color:#00E5FF;font:800 12px/1.4 Inter,Arial,sans-serif;letter-spacing:.17em;text-transform:uppercase}.st-hero__overlay{pointer-events:none}.st-hero__overlay>*{pointer-events:auto}.st-hero__overlay h1{font-family:'Bebas Neue',Impact,sans-serif!important;font-size:clamp(48px,9vw,110px)!important}.st-hero__overlay p{font:clamp(18px,2vw,26px)/1.45 Inter,Arial,sans-serif!important;max-width:760px;color:#fff;text-shadow:0 1px 16px rgba(0,0,0,.65)}.sqb-hero-cta,.sqb-extra-section a{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 24px;border-radius:999px;background:#FFC83D;color:#050A10!important;text-decoration:none;font-weight:800}.sqb-story-heading{max-width:980px;margin:55px auto 0;padding:0 24px;color:#fff}.sqb-story-heading h2{font:clamp(42px,7vw,82px)/.98 'Bebas Neue',Impact,sans-serif;margin:12px 0 16px}.sqb-story-heading>p:last-child{font-size:20px;line-height:1.6;color:#b8c8d2}.sqb-scenes-label{max-width:1160px;margin:24px auto;padding:0 24px}.sqb-disclaimer{color:#9aaab2;font-size:13px;margin-top:22px}.sqb-rollout-intro{max-width:980px;margin:60px auto 24px;padding:0 24px;color:#fff}.sqb-rollout-intro h2,.sqb-extra-section h2,.sqb-login-section h2{font:clamp(42px,7vw,82px)/.98 'Bebas Neue',Impact,sans-serif;margin:12px 0 20px}.sqb-rollout-intro h2 span{color:#FFC83D}.sqb-rollout-intro>p:last-child,.sqb-extra-inner>p{font:clamp(17px,1.7vw,21px)/1.65 Inter,Arial,sans-serif;color:#b8c8d2;max-width:800px}.sqb-rollout-note{max-width:980px;margin:10px auto 40px;padding:0 24px;color:#9aaab2;font-size:13px}.sqb-extra-section,.sqb-login-section{padding:clamp(70px,10vw,130px) max(22px,6vw);background:#050A10;color:#eff8fc}.sqb-extra-inner,.sqb-login-inner{width:min(1160px,100%);margin:auto}.sqb-package-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:32px}.sqb-package,.sqb-sample-grid article{border:1px solid rgba(0,229,255,.2);border-radius:18px;padding:26px;background:linear-gradient(145deg,rgba(12,31,42,.92),rgba(8,14,23,.98));color:#eff8fc}.sqb-package-featured{border-color:#FFC83D;transform:translateY(-8px)}.sqb-package-name,.sqb-sample-grid b{color:#00E5FF;font-size:12px;font-weight:800;letter-spacing:.14em}.sqb-package-price,.sqb-sample-grid strong{display:block;color:#FFC83D;font-size:38px;font-weight:850;margin:12px 0}.sqb-package h3{font-size:24px}.sqb-package ul{padding-left:19px;color:#c2d0d8;line-height:1.85}.sqb-package-partners{margin-top:28px;color:#d7e0e5}.sqb-package-disclaimer,.sqb-rollout-note{font-size:13px;color:#9aaab2}.sqb-order-flow{padding:0 24px 42px;max-width:1100px;margin:auto}.sqb-order-flow .sqb-overline{text-align:center;line-height:1.8}.sqb-sample-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:24px}.sqb-sample-grid strong{font-size:28px}.sqb-sample-grid span,.sqb-sample-grid small{display:block;color:#b8c8d2;margin-top:6px}.sqb-sample-label{text-align:center;color:#FF2E93;font-weight:800;letter-spacing:.12em;font-size:12px;margin-top:22px}.sqb-starlink-intro,.sqb-starlink-foot{max-width:850px;margin:18px auto;color:#b8c8d2;font:18px/1.6 Inter,Arial,sans-serif}.stx-testimonials__quote{padding:26px;border:1px solid rgba(0,229,255,.2);border-radius:16px;background:rgba(8,20,29,.8);margin:18px auto;max-width:940px}.stx-testimonials__text{font-size:clamp(22px,4vw,38px)!important;color:#fff!important}.stx-testimonials__attribution{font:15px/1.6 Inter,Arial,sans-serif!important;color:#b8c8d2!important}.stx-closing-statement__heading{max-width:980px}.stx-closing-statement__manifesto{max-width:800px}.sqb-join-cta{margin-left:12px}.sqb-login-inner>p:not(.sqb-overline){font-size:18px;line-height:1.6;color:#b8c8d2;max-width:760px}.sq-disclosure-footer{display:block!important;width:100%;margin:0;padding:56px max(6vw,22px) 28px;background:#071019!important;color:#a7b5bd!important;border-top:1px solid rgba(0,229,255,.2);font:15px/1.7 Inter,Arial,sans-serif}.sq-disclosure-inner{width:min(980px,100%);margin:0 auto;display:flex;flex-direction:column;align-items:flex-start;gap:12px}.sq-disclosure-inner p{margin:0;color:#a7b5bd}.sq-disclosure-footer a{color:#00E5FF;text-decoration:none}.sq-disclosure-footer a:hover{text-decoration:underline}.sq-disclosure-footer .footer-brand{font:700 30px/1 'Bebas Neue',Impact,sans-serif;color:#fff;letter-spacing:.03em;margin-bottom:8px}.sq-disclosure-links{display:flex;flex-wrap:wrap;gap:10px;align-items:center}.sq-disclosure-footer img{display:block;width:112px;max-width:100%;height:auto;margin-top:12px}.sq-disclosure-footer .footer-end{font-size:12px;color:#6f838e;margin-top:8px}
@media(max-width:760px){.sqb-header-tagline{display:none}.sqb-package-grid,.sqb-sample-grid{grid-template-columns:1fr}.sqb-package-featured{transform:none}.stx-brief-intro{padding:75px 22px!important}.sqb-join-cta{margin:12px 0 0}.st-chpin__stick{position:relative!important;top:auto!important}.st-chpin{height:auto!important}.st-chart{width:100%!important;min-width:0!important;margin:0!important}.st-ch-svg{width:100%!important}}
@media(prefers-reduced-motion:reduce){.st-hero--scroll{height:100svh!important}.st-hero__sticky{position:absolute!important}.st-scbg-l,.st-el{scroll-behavior:auto!important;animation:none!important;transition:none!important}}
</style>
</head><body><div class="v2-wrap">
  <script>try{history.scrollRestoration='manual'}catch(e){}(function(){var t=false;function m(){t=true;}['wheel','touchstart','keydown','pointerdown'].forEach(function(n){addEventListener(n,m,{passive:true});});addEventListener('load',function(){if(!t&&!location.hash)window.scrollTo(0,0);});})();</script>
  <header class="st-header" style="background:#0a0a0a;--hdr-sub-bg:#0a0a0a;color:#f5f5f5;font-family:'Bebas Neue',system-ui,sans-serif"><a class="st-header__logo" href="#cover" style="color:#f5f5f5">SilverQueen.</a><span class="sqb-header-tagline">A BARANGAY-FIRST MOVEMENT</span><nav class="st-header__nav"><a href="#sec-1" style="color:#f5f5f5">Network</a><a href="#auth" style="color:#f5f5f5">Log in</a><a href="#sec-3" style="color:#f5f5f5">Engines</a><a href="#sec-4" style="color:#f5f5f5">How it works</a><a class="st-header__cta" href="#auth" style="background:#ff3366;color:#FFFFFF">Join the Movement</a></nav><button class="st-header__burger" type="button" aria-label="Menu" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button></header>
  <main class="st-canvas">
    <span id="cover" class="st-anchor" aria-hidden="true"></span><span id="sec-1" class="st-anchor" aria-hidden="true"></span>
    <section class="st-hero st-hero--scroll v2-hero" data-element-id="e8dbfaa0-7cbe-4109-8ea1-8bcf5c389de0" style="height:528vh;">
      <div class="st-hero__sticky" style="">
        <canvas class="st-hero__canvas" data-frames-path="/assets/silverqueen/sqb-engine-v2/assets/frames/" data-frames-count="176" data-scrub-vh="528" data-frames-ext="webp" style="background:url('/assets/silverqueen/sqb-engine-v2/assets/frames/frame_0001.webp') var(--st-hero-focus,50%) 50%/cover no-repeat"></canvas>
      <div class="st-hero__overlay"><span class="sqb-hero-kicker">SOCIAL TRADE · LOCAL ENGINES</span><h1>SilverQueen: An Engine in Every Barangay</h1><p>More space to post, sell, and deliver, powered by an SQB Engine that works inside your own radius.</p><a class="sqb-hero-cta" href="#auth">Join the Movement ↗</a></div>
      </div>
    </section>
    <span id="sec-2" class="st-anchor" aria-hidden="true"></span>
    <div class="st-el st-custom" data-element-id="brief-intro" style="margin-top:64px;margin-bottom:64px;"><section class="stx-brief-intro"><p class="sqb-overline">THE DISTANCE BETWEEN US</p>
  <div class="stx-brief-intro__container">
    <h2 class="stx-brief-intro__heading" data-st-reveal>Great barangays. Limited space.</h2>

    <div class="stx-brief-intro__grid">
      <div class="stx-brief-intro__metrics">
        <div class="stx-brief-intro__metric" data-st-reveal style="transition-delay:0.1s">
          <div class="stx-brief-intro__metric-value">40,000+</div>
          <div class="stx-brief-intro__metric-label">BARANGAYS<br>Across the Philippines</div>
        </div>

        <div class="stx-brief-intro__metric" data-st-reveal style="transition-delay:0.2s">
          <div class="stx-brief-intro__metric-value">FREE</div>
          <div class="stx-brief-intro__metric-label">ACCOUNT START<br>Social trade</div>
        </div>

        <div class="stx-brief-intro__metric" data-st-reveal style="transition-delay:0.3s">
          <div class="stx-brief-intro__metric-value">LOCAL</div>
          <div class="stx-brief-intro__metric-label">ENGINE<br>Last-mile route</div>
        </div>
      </div>

      <div class="stx-brief-intro__content" data-st-reveal style="transition-delay:0.4s">
        <p class="stx-brief-intro__paragraph">Free accounts can post, sell, and connect, within limits. When your community, store, or videos outgrow those limits, an SQB Engine gives you room to grow, and keeps the work of serving your barangay local.</p><p class="sqb-disclaimer">A national-scale vision, not a claim of current platform coverage.</p>
      </div>
    </div>
  </div>

  <style>
    .stx-brief-intro {
      background-color: #0a0a0a;
      color: #f5f5f5;
      padding: 120px 24px;
      position: relative;
      overflow: hidden;
    }

    .stx-brief-intro__container {
      max-width: 72rem;
      margin: 0 auto;
    }

    .stx-brief-intro__heading {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(4rem, 10vw, 9rem);
      line-height: 0.9;
      letter-spacing: 0.02em;
      text-transform: uppercase;
      margin: 0 0 80px 0;
      color: #f5f5f5;
      opacity: 0;
      transform: translateY(40px);
      transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1),
                  transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .stx-brief-intro__heading.st-in {
      opacity: 1;
      transform: translateY(0);
    }

    .stx-brief-intro__grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 64px;
      align-items: start;
    }

    @media (min-width: 960px) {
      .stx-brief-intro__grid {
        grid-template-columns: 1fr 1.2fr;
        gap: 80px;
      }
    }

    .stx-brief-intro__metrics {
      display: flex;
      flex-direction: column;
      gap: 56px;
    }

    .stx-brief-intro__metric {
      opacity: 0;
      transform: translateY(30px) scale(0.95);
      transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1),
                  transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .stx-brief-intro__metric.st-in {
      opacity: 1;
      transform: translateY(0) scale(1);
    }

    .stx-brief-intro__metric-value {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(3.5rem, 8vw, 6rem);
      line-height: 1;
      letter-spacing: 0.01em;
      color: #ff3366;
      margin-bottom: 12px;
    }

    .stx-brief-intro__metric-label {
      font-family: 'Inter', sans-serif;
      font-size: clamp(0.95rem, 1.5vw, 1.125rem);
      line-height: 1.5;
      color: #f5f5f5;
      font-weight: 500;
      letter-spacing: 0.01em;
    }

    .stx-brief-intro__content {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1),
                  transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .stx-brief-intro__content.st-in {
      opacity: 1;
      transform: translateY(0);
    }

    .stx-brief-intro__paragraph {
      font-family: 'Inter', sans-serif;
      font-size: clamp(1.125rem, 2vw, 1.375rem);
      line-height: 1.7;
      color: #f5f5f5;
      margin: 0;
      max-width: 38rem;
      font-weight: 400;
      letter-spacing: -0.01em;
    }

    @media (prefers-reduced-motion: reduce) {
      .stx-brief-intro__heading,
      .stx-brief-intro__metric,
      .stx-brief-intro__content {
        transition: none;
        opacity: 1;
        transform: none;
      }
    }
  </style>
</section></div>
    <span id="sec-3" class="st-anchor" aria-hidden="true"></span><div class="sqb-story-heading"><p class="sqb-overline">THREE PILLARS · ONE LOCAL LOOP</p><h2>Every barangay has an engine.</h2><p>Space to grow. Reach to be seen. A local route to the doorstep.</p></div>
    <div class="st-el" data-element-id="stacking-strategy" style="position:relative;margin-top:64px;margin-right:auto;margin-bottom:64px;margin-left:auto;width:50%;max-width:100%;min-width:min(800px,100%);"><div class="st-scbg" aria-hidden="true" data-st-scbg><div class="st-scbg-l" data-i="0" style="background-image:url('/assets/silverqueen/sqb-engine-v2/assets/images/img-9b62334e-f80a-48f6-bfd1-34b233f584cd.png')"></div><div class="st-scbg-l" data-i="1" style="background-image:url('/assets/silverqueen/sqb-engine-v2/assets/images/img-fd2b3148-fb09-4f7a-81fd-b830fec0dc27.png')"></div><div class="st-scbg-l" data-i="2" style="background-image:url('/assets/silverqueen/sqb-engine-v2/assets/images/img-74c5e9a5-3921-4610-bbe6-2f47f07d88e8.png')"></div><div class="st-scbg-l" data-i="3" style="background-image:url('/assets/silverqueen/sqb-engine-v2/assets/images/img-5ad3689c-b885-47b2-839b-b55df16253b6.png')"></div></div><div style="position:relative;z-index:1;display:flex;flex-direction:column"><div data-st-card="0" style="position:sticky;top:24px;z-index:1;display:flex;flex-direction:column;overflow:hidden;padding:8px;border-radius:16px;border:1px solid rgba(255,255,255,0.10);box-shadow:0 -10px 30px rgba(0,0,0,0.28);background:#0a0a0a;min-height:56vh"><div style="position:relative;overflow:hidden;border-radius:10px;text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-9b62334e-f80a-48f6-bfd1-34b233f584cd.png" alt="" loading="lazy" decoding="async" style="width:100%;height:auto;max-height:55vh;object-fit:contain;display:block" /></div><div style="padding:14px 14px 16px;flex-shrink:0"><h3 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">SPACE · ROOM TO GROW</h3><p class="st-text" style="margin-top:6px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Premium engines lift your limits on text, images, and video uploads.</p></div></div><div data-st-card="1" style="position:sticky;top:40px;z-index:2;margin-top:24px;display:flex;flex-direction:column;overflow:hidden;padding:8px;border-radius:16px;border:1px solid rgba(255,255,255,0.10);box-shadow:0 -10px 30px rgba(0,0,0,0.28);background:#0a0a0a;min-height:56vh"><div style="position:relative;overflow:hidden;border-radius:10px;text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-fd2b3148-fb09-4f7a-81fd-b830fec0dc27.png" alt="" loading="lazy" decoding="async" style="width:100%;height:auto;max-height:55vh;object-fit:contain;display:block" /></div><div style="padding:14px 14px 16px;flex-shrink:0"><h3 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">REACH · BE SEEN CLOSE TO HOME</h3><p class="st-text" style="margin-top:6px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Ads delivery favors engine owners. Higher suites get stronger delivery and a wider audience.</p></div></div><div data-st-card="2" style="position:sticky;top:56px;z-index:3;margin-top:24px;display:flex;flex-direction:column;overflow:hidden;padding:8px;border-radius:16px;border:1px solid rgba(255,255,255,0.10);box-shadow:0 -10px 30px rgba(0,0,0,0.28);background:#0a0a0a;min-height:56vh"><div style="position:relative;overflow:hidden;border-radius:10px;text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-74c5e9a5-3921-4610-bbe6-2f47f07d88e8.png" alt="" loading="lazy" decoding="async" style="width:100%;height:auto;max-height:55vh;object-fit:contain;display:block" /></div><div style="padding:14px 14px 16px;flex-shrink:0"><h3 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">LAST MILE · YOUR RADIUS, YOUR ROUTE</h3><p class="st-text" style="margin-top:6px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Each engine serves its own radius, so the last mile stays local, tracked, and paid for.</p></div></div><div data-st-card="3" style="position:sticky;top:72px;z-index:4;margin-top:24px;display:flex;flex-direction:column;overflow:hidden;padding:8px;border-radius:16px;border:1px solid rgba(255,255,255,0.10);box-shadow:0 -10px 30px rgba(0,0,0,0.28);background:#0a0a0a;min-height:56vh"><div style="position:relative;overflow:hidden;border-radius:10px;text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-5ad3689c-b885-47b2-839b-b55df16253b6.png" alt="" loading="lazy" decoding="async" style="width:100%;height:auto;max-height:55vh;object-fit:contain;display:block" /></div><div style="padding:14px 14px 16px;flex-shrink:0"><h3 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">ONE LOCAL LOOP</h3><p class="st-text" style="margin-top:6px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Space to grow. Reach to be seen. A local route to the doorstep.</p></div></div></div></div>
    <span id="sec-4" class="st-anchor" aria-hidden="true"></span><p class="sqb-overline sqb-scenes-label">SPACE · REACH · LAST MILE</p>
    <div class="st-el st-bleed st-fs" data-element-id="execution" style="margin-top:64px;margin-bottom:64px;"><div class="st-sp-scene"><div class="st-sp-text"><h2 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">SOCIAL · COMMUNITY FEEDS</h2><p class="st-text" style="margin-top:12px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Local community feeds and member-to-member sharing keep people connected around the places they call home.</p></div><div class="st-sp-imgcol"><div class="st-sp-img" style="background:rgba(255,255,255,0.05);text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-2a15d155-c99a-4c93-8235-166037d7ab56.png" alt="" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;display:block" /></div></div></div><div class="st-sp-scene rev"><div class="st-sp-text"><h2 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">MARKETPLACE · SELL DIRECTLY</h2><p class="st-text" style="margin-top:12px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Local producers and micro-sellers can sell directly to neighbors.</p></div><div class="st-sp-imgcol"><div class="st-sp-img" style="background:rgba(255,255,255,0.05);text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-908250bb-bd6d-4cb0-8af9-7ea815dd83a9.png" alt="" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;display:block" /></div></div></div><div class="st-sp-scene"><div class="st-sp-text"><h2 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">DELIVERY · BARANGAY ROUTES</h2><p class="st-text" style="margin-top:12px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Rider assignment at the barangay level, with tracking, keeps the last mile local.</p></div><div class="st-sp-imgcol"><div class="st-sp-img" style="background:rgba(255,255,255,0.05);text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-5542ad72-60d5-4dfb-9f7f-4249a0ff4469.png" alt="" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;display:block" /></div></div></div></div>
    <span id="sec-5" class="st-anchor" aria-hidden="true"></span><div class="sqb-rollout-intro"><p class="sqb-overline">AN ENGINE ROLLOUT VISION</p><h2>One engine at a time.<br><span>One network together.</span></h2><p>Start with local engines in Luzon. Link the Visayas. Bring Mindanao into the route. Every engine lights up the barangay next to it.</p></div>
    <div class="st-el st-bleed" data-element-id="work-gallery" style="padding-left:48px;margin-top:64px;margin-bottom:64px;"><div style="overflow:hidden;padding:5px"><div id="car-work-gallery" data-off="0" style="display:flex;gap:20px;transition:transform .5s ease;touch-action:pan-y"><div style="flex:0 0 320px;display:flex;flex-direction:column"><div style="position:relative;height:200px;border-radius:16px;overflow:hidden;background:rgba(255,255,255,0.05);text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-b8d4d391-c446-4325-994d-50733f432575.png" alt="" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;display:block" /></div><div style="padding:14px 2px 0"><h3 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Luzon · First engines</h3><p class="st-text" style="margin-top:4px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">A rollout vision for local engines, connected island by island.</p></div></div><div style="flex:0 0 320px;display:flex;flex-direction:column"><div style="position:relative;height:200px;border-radius:16px;overflow:hidden;background:rgba(255,255,255,0.05);text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-eb43e7e5-d3c2-44e9-823f-099408a4cf8b.png" alt="" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;display:block" /></div><div style="padding:14px 2px 0"><h3 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Visayas · Island links</h3><p class="st-text" style="margin-top:4px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Illustrative community activity around a barangay engine.</p></div></div><div style="flex:0 0 320px;display:flex;flex-direction:column"><div style="position:relative;height:200px;border-radius:16px;overflow:hidden;background:rgba(255,255,255,0.05);text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-805d0c32-f13b-4e17-88c3-9e18323aec4d.png" alt="" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;display:block" /></div><div style="padding:14px 2px 0"><h3 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Mindanao · Regional hubs</h3><p class="st-text" style="margin-top:4px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">An island link connects neighboring local engines.</p></div></div><div style="flex:0 0 320px;display:flex;flex-direction:column"><div style="position:relative;height:200px;border-radius:16px;overflow:hidden;background:rgba(255,255,255,0.05);text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-8590fc85-a69d-4e08-a937-28bbc4533586.png" alt="" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;display:block" /></div><div style="padding:14px 2px 0"><h3 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">One network · Shared connectivity</h3><p class="st-text" style="margin-top:4px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Shared connectivity is a network rollout vision, not a claim of current coverage.</p></div></div><div style="flex:0 0 320px;display:flex;flex-direction:column"><div style="position:relative;height:200px;border-radius:16px;overflow:hidden;background:rgba(255,255,255,0.05);text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-26078440-aa67-4c36-9e80-956b723dc9e6.png" alt="" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;display:block" /></div><div style="padding:14px 2px 0"><h3 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Community feeds</h3><p class="st-text" style="margin-top:4px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Local producers and micro-sellers can sell directly to neighbors.</p></div></div><div style="flex:0 0 320px;display:flex;flex-direction:column"><div style="position:relative;height:200px;border-radius:16px;overflow:hidden;background:rgba(255,255,255,0.05);text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-46caa313-5b25-49e4-9eaa-5682aa3ad874.png" alt="" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;display:block" /></div><div style="padding:14px 2px 0"><h3 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Local sellers</h3><p class="st-text" style="margin-top:4px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Rider assignment begins at the barangay level, with delivery tracking.</p></div></div><div style="flex:0 0 320px;display:flex;flex-direction:column"><div style="position:relative;height:200px;border-radius:16px;overflow:hidden;background:rgba(255,255,255,0.05);text-align:center;width:100%;"><img src="/assets/silverqueen/sqb-engine-v2/assets/images/img-f5f03456-3c69-4612-bbd6-ba8e035abb5c.png" alt="" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;display:block" /></div><div style="padding:14px 2px 0"><h3 class="st-text" style="font-family:'Bebas Neue',system-ui,sans-serif;font-size:2rem;font-weight:700;font-style:normal;line-height:1.2;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Nearby rider routes</h3><p class="st-text" style="margin-top:4px;font-family:'Inter',system-ui,sans-serif;font-size:1.125rem;font-weight:400;font-style:normal;line-height:1.6;text-align:left;text-transform:none;text-decoration:none;color:#f5f5f5;width:100%;">Community commerce, connected through local routes.</p></div></div></div></div><div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;padding-right:40px"><button onclick="(function(){var t=document.getElementById('car-work-gallery');var c=parseInt(t.dataset.off)||0;c=Math.min(0,c+340);t.dataset.off=c;t.style.transform='translateX('+c+'px)'})()" class="st-car-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"></polyline></svg></button><button onclick="(function(){var t=document.getElementById('car-work-gallery');var vp=t.parentElement;var c=parseInt(t.dataset.off)||0;var max=-(t.scrollWidth-vp.clientWidth);c=Math.max(max,c-340);t.dataset.off=c;t.style.transform='translateX('+c+'px)'})()" class="st-car-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,6 15,12 9,18"></polyline></svg></button></div><script>(function(){var t=document.getElementById('car-work-gallery');if(!t)return;var sx=0,sy=0,dr=false,ax=0;t.addEventListener('touchstart',function(e){sx=e.touches[0].clientX;sy=e.touches[0].clientY;dr=true;ax=0;t.style.transition='none'},{passive:true});t.addEventListener('touchmove',function(e){if(!dr)return;var dx=e.touches[0].clientX-sx,dy=e.touches[0].clientY-sy;if(!ax){if(Math.abs(dx)<6&&Math.abs(dy)<6)return;ax=Math.abs(dx)>Math.abs(dy)?1:-1;if(ax<0){dr=false;t.style.transition='transform .5s ease';t.style.transform='translateX('+(parseInt(t.dataset.off)||0)+'px)';return}}var c=parseInt(t.dataset.off)||0;t.style.transform='translateX('+(c+dx)+'px)'},{passive:true});function fin(e){if(!dr){ax=0;return}dr=false;t.style.transition='transform .5s ease';var dx=(e.changedTouches&&e.changedTouches[0]?e.changedTouches[0].clientX:sx)-sx;var vp=t.parentElement;var c=parseInt(t.dataset.off)||0;if(ax>0&&Math.abs(dx)>50){var max=-(t.scrollWidth-vp.clientWidth);if(dx<0)c=Math.max(max,c-340);else c=Math.min(0,c+340)}t.dataset.off=c;t.style.transform='translateX('+c+'px)';ax=0}t.addEventListener('touchend',fin,{passive:true});t.addEventListener('touchcancel',fin,{passive:true})})()</script></div>
    <section class="sqb-extra-section sqb-packages" id="packages"><div class="sqb-extra-inner"><p class="sqb-overline">CHOOSE YOUR ENGINE</p><h2>Three suites. One growing network.</h2><div class="sqb-package-grid"><article class="sqb-package"><p class="sqb-package-name">SILVERSUITE</p><div class="sqb-package-price">$130</div><h3>Start your engine.</h3><ul><li>Space: <?= $escV2($sqbV2Placeholders['silver_space']) ?></li><li>Ads delivery: Boosted</li><li>Slice auto-allocation: Included</li><li>Last-mile capacity credits: Eligible</li></ul><a href="/register?suite=silver">Choose Silver</a></article><article class="sqb-package sqb-package-featured"><p class="sqb-package-name">GOLDSUITE · FEATURED</p><div class="sqb-package-price">$260</div><h3>Grow your radius.</h3><ul><li>Space: <?= $escV2($sqbV2Placeholders['gold_space']) ?></li><li>Ads delivery: Stronger, wider audience</li><li>Slice auto-allocation: Included</li><li>Last-mile capacity credits: Higher allocation</li></ul><a href="/register?suite=gold">Choose Gold</a></article><article class="sqb-package"><p class="sqb-package-name">PLATINUMSUITE</p><div class="sqb-package-price">$1018</div><h3>Anchor your barangay.</h3><ul><li>Space: <?= $escV2($sqbV2Placeholders['platinum_space']) ?></li><li>Ads delivery: Highest priority, widest audience</li><li>Slice auto-allocation: Included</li><li>Last-mile capacity credits: Highest allocation</li><li>Starlink Connect: Eligible (see next section)</li></ul><a href="/register?suite=platinum">Choose Platinum</a></article></div><p class="sqb-package-partners">Engines are available to anyone. Merchant partners nationwide may host engines for their area.</p><p class="sqb-package-disclaimer">No guarantee of earnings, ad delivery results, or coverage.</p></div></section><span id="sec-6" class="st-anchor" aria-hidden="true"></span>
    <div class="st-el st-custom" data-element-id="impact-numbers" style="margin-top:64px;margin-bottom:64px;"><section class="stx-impact-numbers">
  <div class="stx-impact-numbers__container">
    <h2 class="stx-impact-numbers__heading" data-st-reveal>FREE TO START</h2>

    <div class="stx-impact-numbers__stats">
      <div class="stx-impact-numbers__stat-row" data-stat-row="1">
        <div class="stx-impact-numbers__number" data-counter >FREE</div>
        <div class="stx-impact-numbers__label">Free posting and media limits</div>
      </div>

      <div class="stx-impact-numbers__stat-row" data-stat-row="2">
        <div class="stx-impact-numbers__number stx-impact-numbers__number--purple" data-counter >MORE</div>
        <div class="stx-impact-numbers__label">Higher limits with premium</div>
      </div>

      <div class="stx-impact-numbers__stat-row" data-stat-row="3">
        <div class="stx-impact-numbers__number stx-impact-numbers__number--yellow" data-counter >LOCAL</div>
        <div class="stx-impact-numbers__label">Priority ads delivery for engine owners</div>
      </div>
    </div>

    <div class="stx-impact-numbers__summary" data-st-reveal>
      <p>Free accounts have limits on post length, image count, and video uploads. They're generous, but capped. Premium members get higher limits and automatic slice allocation, and engine owners get priority in ads delivery.</p>
    </div>
  </div>

  <style>
    .stx-impact-numbers {
      background: linear-gradient(180deg, rgba(26, 26, 26, 0.3) 0%, rgba(10, 10, 10, 0) 100%);
      padding: 120px 24px;
      position: relative;
      overflow: hidden;
    }

    .stx-impact-numbers__container {
      max-width: 72rem;
      margin: 0 auto;
    }

    .stx-impact-numbers__heading {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(3rem, 8vw, 7rem);
      color: #f5f5f5;
      margin: 0 0 80px 0;
      letter-spacing: 0.02em;
      line-height: 0.95;
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1), transform 0.8s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .stx-impact-numbers__heading.st-in {
      opacity: 1;
      transform: translateY(0);
    }

    .stx-impact-numbers__stats {
      margin-bottom: 100px;
    }

    .stx-impact-numbers__stat-row {
      padding: 60px 0;
      border-bottom: 1px solid rgba(245, 245, 245, 0.08);
      opacity: 0;
      transform: translateY(40px);
      transition: opacity 0.9s cubic-bezier(0.22, 1, 0.36, 1), transform 0.9s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .stx-impact-numbers__stat-row:first-child {
      padding-top: 0;
    }

    .stx-impact-numbers__stat-row:last-child {
      border-bottom: none;
    }

    .stx-impact-numbers__stat-row.stx-impact-numbers--visible {
      opacity: 1;
      transform: translateY(0);
    }

    .stx-impact-numbers__number {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(5rem, 16vw, 14rem);
      line-height: 0.9;
      color: #ff3366;
      margin-bottom: 16px;
      font-weight: 400;
      letter-spacing: -0.02em;
      display: block;
    }

    .stx-impact-numbers__number--purple {
      color: #8833ff;
    }

    .stx-impact-numbers__number--yellow {
      color: #ffcc00;
    }

    .stx-impact-numbers__label {
      font-family: 'Inter', sans-serif;
      font-size: clamp(1.125rem, 2.5vw, 1.5rem);
      color: #f5f5f5;
      line-height: 1.4;
      max-width: 42rem;
      font-weight: 500;
      letter-spacing: -0.01em;
    }

    .stx-impact-numbers__summary {
      max-width: 52rem;
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.2s, transform 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.2s;
    }

    .stx-impact-numbers__summary.st-in {
      opacity: 1;
      transform: translateY(0);
    }

    .stx-impact-numbers__summary p {
      font-family: 'Inter', sans-serif;
      font-size: clamp(1rem, 1.8vw, 1.125rem);
      line-height: 1.7;
      color: rgba(245, 245, 245, 0.85);
      margin: 0;
    }

    @media (max-width: 768px) {
      .stx-impact-numbers {
        padding: 80px 24px;
      }

      .stx-impact-numbers__heading {
        margin-bottom: 60px;
      }

      .stx-impact-numbers__stats {
        margin-bottom: 60px;
      }

      .stx-impact-numbers__stat-row {
        padding: 40px 0;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .stx-impact-numbers__heading,
      .stx-impact-numbers__stat-row,
      .stx-impact-numbers__summary {
        transition: none;
        opacity: 1;
        transform: none;
      }
    }
  </style>


</section></div>
    <span id="sec-7" class="st-anchor" aria-hidden="true"></span>
    <div class="st-chpin" id="trk-ch-results-chart"><div class="st-chpin__stick"><div class="st-el st-chart" data-element-id="results-chart" style="background-color:rgba(255,255,255,0.03);padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px;margin-top:64px;margin-right:auto;margin-bottom:64px;margin-left:auto;width:72%;max-width:100%;border-radius:14px;min-width:min(680px,100%);"><div style="margin-bottom:20px"><div style="font-size:22px;font-weight:700;line-height:1.25;font-family:'Bebas Neue',system-ui,sans-serif">THE BARANGAY ENGINE · SAMPLE ORDER FLOW</div><div style="margin-top:6px;font-size:15px;line-height:1.5;opacity:.65">A neighborhood order moves through one connected loop, from a seller's shelf to a nearby rider, with the local engine handling the capacity.</div></div><svg id="ch-results-chart" class="st-ch-svg" viewBox="0 0 600 240" preserveAspectRatio="xMidYMid meet"><defs><linearGradient id="g-ch-results-chart" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#00E5FF" stop-opacity="0.35"/><stop offset="100%" stop-color="#00E5FF" stop-opacity="0"/></linearGradient></defs><polygon class="st-ch-area" points="12,240 12,120 108,120 204,120 300,120 396,120 492,120 588,120 588,240" fill="url(#g-ch-results-chart)"/><polyline class="st-ch-line" points="12,120 108,120 204,120 300,120 396,120 492,120 588,120" fill="none" stroke="#00E5FF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="--len:629"/><circle class="st-ch-dot" cx="12" cy="120" r="4.5" fill="#00E5FF"/><circle class="st-ch-dot" cx="108" cy="120" r="4.5" fill="#00E5FF"/><circle class="st-ch-dot" cx="204" cy="120" r="4.5" fill="#00E5FF"/><circle class="st-ch-dot" cx="300" cy="120" r="4.5" fill="#00E5FF"/><circle class="st-ch-dot" cx="396" cy="120" r="4.5" fill="#00E5FF"/><circle class="st-ch-dot" cx="492" cy="120" r="4.5" fill="#00E5FF"/><circle class="st-ch-dot" cx="588" cy="120" r="4.5" fill="#00E5FF"/></svg><div class="st-ch-xlabels"><span>Placed</span><span>Seller confirms</span><span>Rider assigned</span><span>Pickup</span><span>In transit</span><span>Nearby</span><span>Delivered</span></div><script>(function(){var e=document.getElementById('ch-results-chart');var t=document.getElementById('trk-ch-results-chart');if(!e||!t)return;var r=false;function u(){r=false;var vh=window.innerHeight;var b=t.getBoundingClientRect();var span=b.height-vh;var p=span>60?Math.min(1,Math.max(0,(-b.top)/span)):((vh-b.top)/(vh*0.6));p=Math.min(1,Math.max(0,p));e.style.setProperty('--p',Math.min(1,p/0.8).toFixed(3))}function s(){if(r)return;r=true;requestAnimationFrame(u)}window.addEventListener('scroll',s,{passive:true});window.addEventListener('resize',s);u()})()</script></div></div></div>
    <span id="sec-8" class="st-anchor" aria-hidden="true"></span>
    <div class="st-el st-custom" data-element-id="testimonials" style="margin-top:64px;margin-bottom:64px;"><section class="stx-testimonials">
  <div class="stx-testimonials__container">
    <p class="sqb-overline">STARLINK CONNECT · IN DEVELOPMENT</p><h2 class="stx-testimonials__heading" data-st-reveal>Connectivity for the last mile.</h2><p class="sqb-starlink-intro">We're working with Starlink distributors to bring shared connectivity to PlatinumSuite communities.</p>

    <div class="stx-testimonials__quote" data-st-reveal style="transition-delay:0.15s">
      <blockquote class="stx-testimonials__text">5–10 PlatinumSuite holders within 1 KM</blockquote>
      <cite class="stx-testimonials__attribution">
        <span class="stx-testimonials__dash">01 / 03</span>A shared community kiosk with Starlink access and extended tunneled service. It pauses when power drops and resumes when it returns.</cite>
    </div>

    <div class="stx-testimonials__quote" data-st-reveal style="transition-delay:0.3s">
      <blockquote class="stx-testimonials__text">12–20 PlatinumSuite holders</blockquote>
      <cite class="stx-testimonials__attribution">
        <span class="stx-testimonials__dash">02 / 03</span>A 24/7 solar-backed setup with expandable tunneled Starlink, tracking, and POS tools.</cite>
    </div>

    <div class="stx-testimonials__quote" data-st-reveal style="transition-delay:0.45s">
      <blockquote class="stx-testimonials__text">Collaborator groups above ₱1M / $1M in regional sales</blockquote>
      <cite class="stx-testimonials__attribution">
        <span class="stx-testimonials__dash">03 / 03</span>A regional Solar Powered Hub and Market Park in a low-traffic area, with a proposed Ginto Solar Hub.</cite>
    </div>
  </div>

    <p class="sqb-starlink-foot">Built with our network of 300+ solar merchants. Availability varies by location and distributor approval.</p>
  <style>
    .stx-testimonials {
      background: linear-gradient(180deg, rgba(136, 51, 255, 0.02) 0%, rgba(26, 26, 26, 0.4) 100%);
      padding: clamp(5rem, 12vw, 9rem) 1.5rem;
      position: relative;
      overflow: hidden;
    }

    .stx-testimonials__container {
      max-width: 72rem;
      margin: 0 auto;
    }

    .stx-testimonials__heading {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(3.5rem, 8vw, 7rem);
      line-height: 0.9;
      letter-spacing: 0.02em;
      color: #f5f5f5;
      margin: 0 0 clamp(3rem, 8vw, 6rem) 0;
      font-weight: 400;
      text-transform: uppercase;
      opacity: 0;
      transform: translateY(2rem);
      transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .stx-testimonials__heading.st-in {
      opacity: 1;
      transform: translateY(0);
    }

    .stx-testimonials__quote {
      margin-bottom: clamp(4rem, 10vw, 8rem);
      opacity: 0;
      transform: translateY(3rem);
      transition: opacity 1s cubic-bezier(0.16, 1, 0.3, 1), transform 1s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .stx-testimonials__quote.st-in {
      opacity: 1;
      transform: translateY(0);
    }

    .stx-testimonials__quote:last-of-type {
      margin-bottom: 0;
    }

    .stx-testimonials__text {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(2rem, 5.5vw, 4.5rem);
      line-height: 1.15;
      letter-spacing: 0.01em;
      color: #f5f5f5;
      font-style: normal;
      margin: 0 0 clamp(1.5rem, 3vw, 2.5rem) 0;
      font-weight: 400;
      max-width: 62rem;
    }

    .stx-testimonials__attribution {
      font-family: 'Inter', sans-serif;
      font-size: clamp(0.95rem, 1.8vw, 1.15rem);
      line-height: 1.6;
      color: rgba(245, 245, 245, 0.65);
      font-style: normal;
      display: block;
      max-width: 50rem;
    }

    .stx-testimonials__dash {
      color: #ff3366;
      font-weight: 500;
      margin-right: 0.4em;
    }

    @media (prefers-reduced-motion: reduce) {
      .stx-testimonials__heading,
      .stx-testimonials__quote {
        transition: none;
        opacity: 1;
        transform: none;
      }
    }

    @media (min-width: 48rem) {
      .stx-testimonials__attribution {
        padding-left: 2rem;
      }
    }
  </style>
</section></div>
    <span id="sec-9" class="st-anchor" aria-hidden="true"></span>
    <div class="st-el st-custom" data-element-id="closing-statement" style="text-align:center;padding-top:120px;padding-bottom:120px;margin-top:64px;margin-bottom:64px;"><section class="stx-closing-statement">
  <div class="stx-closing-statement__container">
    <p class="sqb-overline">THE NEXT CONNECTION STARTS HERE</p><h2 class="stx-closing-statement__heading" data-st-reveal>Power every community. Bridge every island.</h2>
    <p class="stx-closing-statement__manifesto" data-st-reveal style="transition-delay:0.15s">A more connected Philippines begins with the people already making their barangay move. Space. Reach. Last mile.</p>
    <div class="stx-closing-statement__cta-wrapper" data-st-reveal style="transition-delay:0.3s">
      <a href="#packages" class="stx-closing-statement__cta">Choose Your Suite ↓</a><a class="stx-closing-statement__cta sqb-join-cta" href="#auth">Join the Movement ↗</a>
    </div>
  </div>
  <style>
    .stx-closing-statement {
      background-color: #0a0a0a;
      color: #f5f5f5;
      padding: clamp(100px, 15vh, 180px) 24px;
      position: relative;
      overflow: hidden;
    }

    .stx-closing-statement::before {
      content: '';
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 1px;
      height: 80px;
      background: linear-gradient(to bottom, transparent, #ff3366, transparent);
      opacity: 0.4;
    }

    .stx-closing-statement__container {
      max-width: 56rem;
      margin: 0 auto;
      text-align: center;
    }

    .stx-closing-statement__heading {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(3rem, 8vw, 6rem);
      line-height: 0.95;
      letter-spacing: 0.02em;
      color: #f5f5f5;
      margin: 0 0 clamp(48px, 8vh, 80px) 0;
      font-weight: 400;
    }

    .stx-closing-statement__manifesto {
      font-family: 'Inter', sans-serif;
      font-size: clamp(1.125rem, 2.2vw, 1.5rem);
      line-height: 1.75;
      color: #f5f5f5;
      margin: 0 0 clamp(48px, 8vh, 72px) 0;
      font-weight: 400;
      max-width: 52rem;
      margin-left: auto;
      margin-right: auto;
    }

    .stx-closing-statement__cta-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .stx-closing-statement__cta {
      display: inline-block;
      padding: 18px 48px;
      background-color: #ff3366;
      color: #ffffff;
      font-family: 'Inter', sans-serif;
      font-size: 1rem;
      font-weight: 600;
      text-decoration: none;
      border-radius: 50px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      letter-spacing: 0.01em;
      box-shadow: 0 4px 20px rgba(255, 51, 102, 0.3);
    }

    .stx-closing-statement__cta:hover {
      background-color: #e62958;
      transform: translateY(-2px);
      box-shadow: 0 8px 30px rgba(255, 51, 102, 0.4);
    }

    .stx-closing-statement__cta:active {
      transform: translateY(0);
    }

    .stx-closing-statement [data-st-reveal] {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .stx-closing-statement [data-st-reveal].st-in {
      opacity: 1;
      transform: translateY(0);
    }

    @media (prefers-reduced-motion: reduce) {
      .stx-closing-statement [data-st-reveal] {
        opacity: 1;
        transform: none;
        transition: none;
      }
      .stx-closing-statement__cta {
        transition: none;
      }
    }

    @media (max-width: 640px) {
      .stx-closing-statement {
        padding: clamp(80px, 12vh, 120px) 20px;
      }
      .stx-closing-statement__cta {
        padding: 16px 36px;
        font-size: 0.9375rem;
      }
    }
  </style>
</section></div>
  <script>(function(){if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches){document.querySelectorAll('canvas[data-frames-count]').forEach(function(c){c.setAttribute('data-frames-count','1');});}})();
(function () {
  document.querySelectorAll('canvas[data-frames-path]').forEach(function (canvas) {
    var base = canvas.getAttribute('data-frames-path');
    var count = parseInt(canvas.getAttribute('data-frames-count'), 10) || 0;
    var ext = canvas.getAttribute('data-frames-ext') || 'webp';
    var section = canvas.closest('.st-hero--scroll') || canvas.closest('.st-hero') || canvas;
    if (!count || !canvas.getContext) return;

    var ctx = canvas.getContext('2d');
    var cache = new Map();
    var pending = new Set();
    var failed = new Set();
    var queue = new Set();
    var active = 0;
    var maxActive = 5;
    var maxCache = 24;
    var wanted = 0;
    var previous = 0;
    var current = -1;
    var width = 0;
    var height = 0;
    var raf = 0;

    function clamp(n, lo, hi) { return Math.max(lo, Math.min(hi, n)); }
    function url(i) { return base + 'frame_' + String(i + 1).padStart(4, '0') + '.' + ext; }
    function distance(i) { return Math.abs(i - wanted); }

    function drawNearest() {
      var best = -1;
      var bestDistance = Infinity;
      cache.forEach(function (img, i) {
        var d = Math.abs(i - wanted);
        if (d < bestDistance) { best = i; bestDistance = d; }
      });
      if (best < 0 || best === current) return;
      var img = cache.get(best);
      if (!img || !img.naturalWidth) return;
      if (width !== img.naturalWidth || height !== img.naturalHeight) {
        width = canvas.width = img.naturalWidth;
        height = canvas.height = img.naturalHeight;
      }
      try { ctx.drawImage(img, 0, 0, width, height); current = best; } catch (e) {}
    }

    function trimCache() {
      if (cache.size <= maxCache) return;
      var keys = Array.from(cache.keys()).sort(function (a, b) { return distance(b) - distance(a); });
      while (cache.size > maxCache && keys.length) {
        var key = keys.shift();
        var img = cache.get(key);
        if (img) img.src = '';
        cache.delete(key);
        if (current === key) current = -1;
      }
    }

    function pump() {
      if (!queue.size) return;
      var jobs = Array.from(queue).sort(function (a, b) { return distance(a) - distance(b); });
      while (active < maxActive && jobs.length) {
        var i = jobs.shift();
        queue.delete(i);
        if (cache.has(i) || pending.has(i) || failed.has(i)) continue;
        pending.add(i);
        active++;
        var img = new Image();
        img.decoding = 'async';
        img.onload = function (index, image) {
          var finish = function () {
            pending.delete(index);
            active--;
            cache.set(index, image);
            trimCache();
            drawNearest();
            pump();
          };
          if (image.decode) image.decode().then(finish, finish); else finish();
        }.bind(null, i, img);
        img.onerror = function (index) {
          pending.delete(index);
          failed.add(index);
          active--;
          pump();
        }.bind(null, i);
        img.src = url(i);
      }
    }

    function scheduleWindow() {
      if (raf) cancelAnimationFrame(raf);
      raf = requestAnimationFrame(function () {
        raf = 0;
        var rect = section.getBoundingClientRect();
        var travel = Math.max(1, section.offsetHeight - window.innerHeight);
        var progress = clamp(-rect.top / travel, 0, 1);
        wanted = Math.round(progress * (count - 1));
        var direction = wanted >= previous ? 1 : -1;
        previous = wanted;
        queue.forEach(function (i) { if (Math.abs(i - wanted) > 14) queue.delete(i); });
        // Load the current frame first, then a short buffer in the scroll direction.
        var from = direction > 0 ? wanted - 2 : wanted - 10;
        var to = direction > 0 ? wanted + 10 : wanted + 2;
        for (var i = Math.max(0, from); i <= Math.min(count - 1, to); i++) {
          if (!cache.has(i) && !pending.has(i) && !failed.has(i)) queue.add(i);
        }
        drawNearest();
        pump();
      });
    }

    // Make the opening frame visible promptly while adjacent frames decode in the background.
    for (var i = 0; i < Math.min(count, 8); i++) queue.add(i);
    pump();
    window.addEventListener('scroll', scheduleWindow, { passive: true });
    window.addEventListener('resize', scheduleWindow, { passive: true });
    scheduleWindow();
  });
})();
</script>
<script>
(function(){
  var ws=[].slice.call(document.querySelectorAll('[data-st-scbg]'));
  if(!ws.length)return;
  function tick(){
    var mid=window.innerHeight*0.5;
    ws.forEach(function(bg){
      var w=bg.parentElement;if(!w)return;
      var cards=[].slice.call(w.querySelectorAll('[data-st-card]'));
      var layers=[].slice.call(bg.querySelectorAll('.st-scbg-l'));
      if(!cards.length||!layers.length)return;
      var act=-1;
      for(var i=0;i<cards.length;i++){ if(cards[i].getBoundingClientRect().top<=mid) act=i; }
      if(act<0) act=0;
      layers.forEach(function(l){ l.classList.toggle('on', +l.getAttribute('data-i')===act); });
    });
  }
  var raf=null;
  function onS(){ if(raf)return; raf=requestAnimationFrame(function(){raf=null;tick();}); }
  window.addEventListener('scroll',onS,{passive:true});
  window.addEventListener('resize',onS);
  tick();
})();
</script>          <section class="sqb-login-section" id="auth"><div class="sqb-login-inner"><p class="sqb-overline">YOUR ENGINE STARTS HERE</p><h2>Welcome to SilverQueen</h2><p>Sign in to manage your engine, or join the movement and connect with your barangay.</p><?php require __DIR__ . '/user/login_form_partial.php'; ?></div></section></main>  <footer class="site-footer sq-disclosure-footer" aria-label="SilverQueen and Ginto public disclosures">
    <div class="sq-disclosure-inner">
      <a class="footer-brand" href="#cover">SilverQueen</a>
      <p><strong>silverqueen.pro</strong> is the full implementation of <a href="https://github.com/oliverbob/ginto.ai" target="_blank" rel="noopener noreferrer">Ginto AI</a>, the first business example implementing the full scale and power of the project.</p>
      <p><strong>sq.silverqueen.pro</strong> and <strong>gk.silverqueen.pro</strong> are child projects built around the same projects by GitHub user <strong>oliverbob</strong>.</p>
      <p>The vision is to help digitize the <strong>42,064 barangays</strong> of the Philippines, while welcoming project ventures around the world that strictly do not involve venture capitalists.</p>
      <p>The author’s stage name is <strong>Bob Reyes</strong>, also known as <strong>Datu Gyud</strong> in SilverQueen.</p>
      <p>These projects are run and managed by their current operator, <strong>Ginto Services Corporation</strong> (Company Reg. No. <strong>2026040246055-25</strong>), registered in the Philippines in April 2026 to accommodate the potential of Ginto AI. This does not automatically make the corporation the owner of Ginto AI: Ginto AI is Bob Reyes’s open-source project and predates SilverQueen.</p>
      <p>All online transactions are reported to the <strong>Bureau of Internal Revenue</strong> for full compliance.</p>
      <p>For public disclosures, see <a href="https://sq.silverqueen.pro" target="_blank" rel="noopener noreferrer">sq.silverqueen.pro</a> or use these direct links:</p>
      <nav class="sq-disclosure-links" aria-label="Public disclosures">
        <a href="https://sq.silverqueen.pro/terms" target="_blank" rel="noopener noreferrer">Terms &amp; User Policy</a><span aria-hidden="true">·</span>
        <a href="https://sq.silverqueen.pro/privacy" target="_blank" rel="noopener noreferrer">Privacy</a><span aria-hidden="true">·</span>
        <a href="https://sq.silverqueen.pro/about" target="_blank" rel="noopener noreferrer">About</a>
      </nav>
      <img src="https://comchain.silverqueen.pro/assets/img/bir-registration-seal.png" alt="BIR Registration Seal" loading="lazy">
      <span class="footer-end">Illustrative concept · Philippines</span>
    </div>
  </footer>
  <footer class="st-footer" style="background:rgba(255,255,255,0.03);color:#9CA3AF"><div>© 2026 SilverQueen</div></footer>
<script>
(function(){
  if(!('IntersectionObserver' in window)){var a=document.querySelectorAll('[data-st-reveal]');for(var i=0;i<a.length;i++)a[i].classList.add('st-in');return;}
  var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('st-in');io.unobserve(e.target);}});},{threshold:0.12});
  function scan(){document.querySelectorAll('[data-st-reveal]:not(.st-in)').forEach(function(el){io.observe(el);});}
  scan();
  new MutationObserver(scan).observe(document.body,{childList:true,subtree:true});
})();
</script>
<script>
(function(){
  document.addEventListener('click',function(e){
    var a=e.target&&e.target.closest?e.target.closest('a[href^="#"]'):null;
    if(!a)return;
    var href=a.getAttribute('href')||'';
    if(href.length<2){e.preventDefault();window.scrollTo({top:0,behavior:'smooth'});return;}
    /* "#el-<elementId>" targets a section by IDENTITY rather than by position. #sec-N is assigned in
       document order, so reordering or deleting an element silently repoints every link below it —
       a menu item labelled "Chapter 3" starts scrolling to whatever moved into slot 3. Elements
       already carry data-element-id, so this resolves without emitting a second anchor span (which
       would break `.st-canvas>.st-anchor:first-child+.st-hero`). #sec-N still works for older links.
       ES5 only in here. */
    var el=href.indexOf('#el-')===0
      ? document.querySelector('[data-element-id="'+href.slice(4).replace(/"/g,'')+'"]')
      : document.getElementById(href.slice(1));
    if(el){e.preventDefault();el.scrollIntoView({behavior:'smooth',block:'start'});}
  });
})();
</script><style>
  html.st-embed{scrollbar-width:none;-ms-overflow-style:none}
  html.st-embed::-webkit-scrollbar{display:none;width:0;height:0}
  .st-embed-hint{position:fixed;inset:0;z-index:2147483000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;
    background:radial-gradient(ellipse at center,rgba(0,0,0,.18) 0%,rgba(0,0,0,.52) 100%);
    color:#fff;font:600 17px/1.3 -apple-system,system-ui,sans-serif;letter-spacing:.02em;text-align:center;
    text-shadow:0 1px 8px rgba(0,0,0,.6);pointer-events:none;opacity:0;transition:opacity .5s ease}
  .st-embed-hint__mouse{width:30px;height:50px;border:2.5px solid rgba(255,255,255,.95);border-radius:18px;position:relative;box-shadow:0 2px 12px rgba(0,0,0,.35)}
  .st-embed-hint__mouse:after{content:"";position:absolute;left:50%;top:8px;width:5px;height:10px;margin-left:-2.5px;border-radius:3px;background:#fff;animation:st-embed-wheel 1.6s ease-in-out infinite}
  .st-embed-hint__chev{width:16px;height:16px;border-right:2.5px solid rgba(255,255,255,.9);border-bottom:2.5px solid rgba(255,255,255,.9);transform:rotate(45deg);animation:st-embed-chev 1.6s ease-in-out infinite;margin-top:-8px}
  @keyframes st-embed-wheel{0%{transform:translateY(0);opacity:1}65%{transform:translateY(15px);opacity:0}100%{transform:translateY(0);opacity:0}}
  @keyframes st-embed-chev{0%,100%{opacity:.45;margin-top:-8px}50%{opacity:1;margin-top:-2px}}
</style>
<script>
(function(){
  if(!/[?&]embed=1(&|$)/.test(location.search))return;
  document.documentElement.classList.add('st-embed');
  /* Embedded viewports shrink 1vh, so the vh-based scrub travel collapses and one wheel tick
     jumps many frames. In embed mode, pin the scrub to an ABSOLUTE travel: the page's scrub vh
     (data-scrub-vh, from StoryHtmlBuilder.HeroScrubVh) at 10px per vh — a 1000px reference
     viewport, the same that made 3vh per frame 30px — or, on pages built before that attribute
     existed, 30px per frame. Height = viewport + travel, so travel is the same regardless of
     iframe size. The scrub script reads offsetHeight live, so no other change is needed. */
  var vp=Math.max(1,window.innerHeight);
  function resize(){document.querySelectorAll('.st-hero--scroll').forEach(function(s){
    var c=s.querySelector('canvas[data-frames-count]');
    var n=c?parseInt(c.getAttribute('data-frames-count'),10)||0:0;
    var sv=c?parseInt(c.getAttribute('data-scrub-vh'),10)||0:0;
    var travel=sv>0?sv*10:n*30;
    /* A scroll-away hero needs the hold box's whole travel on top of the scrub, or the section is
       SHORTER than the .st-hero__awayhold inside it and the departing content keeps sticking past
       the section's end, over whatever follows. The multiplier is interpolated from
       HeroAwayHoldVh — it used to be a hard-coded 2.5 and silently went stale the moment the hold
       changed. ES5 only in here: className.indexOf, no arrow/let/const. */
    if(n>0)s.style.height=(Math.max(vp*2,vp+travel)+(s.className.indexOf('st-hero--away')>=0?vp*9:0))+'px';});}
  resize();
  addEventListener('resize',function(){vp=Math.max(1,window.innerHeight);resize();});
  /* Full-frame "scroll to explore" overlay — covers the whole embed until the first interaction. */
  var h=document.createElement('div');
  h.className='st-embed-hint';
  h.setAttribute('aria-hidden','true');
  h.innerHTML='<div class="st-embed-hint__mouse"></div><div>Scroll to explore</div><div class="st-embed-hint__chev"></div>';
  document.body.appendChild(h);
  setTimeout(function(){h.style.opacity='1';},80);
  var done=false;
  function hide(){if(done)return;done=true;h.style.opacity='0';setTimeout(function(){h.remove();},550);}
  /* Arm dismissal AFTER load noise: scroll restoration and the scrub hero fire 'scroll' during
     load, which would insta-hide the overlay. Gestures arm at 700ms; bare 'scroll' additionally
     needs real distance from where the page settled. */
  setTimeout(function(){
    var y0=window.pageYOffset||document.documentElement.scrollTop||0;
    addEventListener('scroll',function(){
      var y=window.pageYOffset||document.documentElement.scrollTop||0;
      if(Math.abs(y-y0)>8)hide();
    },{passive:true});
    ['wheel','touchmove','keydown'].forEach(function(ev){addEventListener(ev,hide,{passive:true});});
  },700);
  setTimeout(hide,10000);
})();
</script><script id="st-price-sync">(function(){
var q=new URLSearchParams(location.search);
var cur=q.get('currency'),base=q.get('base_currency');
if(!cur||!base||cur.toUpperCase()===base.toUpperCase())return;
var loc=q.get('locale')||document.documentElement.lang||'en';
function fmt(c){try{return new Intl.NumberFormat(loc,{style:'currency',currency:cur.toUpperCase()}).format(c/100)}catch(e){return (c/100).toFixed(2)+' '+cur.toUpperCase()}}
var RX=/(?:€|CHF|Fr\.?|EUR|GBP|£|\$|USD)\s?\d[\d.,'’\u00A0]*|\d[\d.,'’\u00A0]*\s?(?:€|CHF|Fr\.?|EUR|GBP|£|\$|USD)/;
function handleOf(a){var m=(a.getAttribute('href')||'').match(/\/products\/([A-Za-z0-9._-]+)/);return m?m[1]:null}
var jobs=[],used=new Set();
document.querySelectorAll('.stcg-price').forEach(function(p){
  var scope=p.parentElement;if(!scope)return;
  var a=scope.querySelector('a[href*="/products/"]');var h=a&&handleOf(a);
  if(h&&RX.test(p.textContent)){jobs.push({n:p,h:h});used.add(p)}
});
document.querySelectorAll('a[href*="/products/"]').forEach(function(a){
  var h=handleOf(a);if(!h)return;
  var n=a,d=0;
  while(n.parentElement&&d<6){
    n=n.parentElement;d++;
    var w=document.createTreeWalker(n,NodeFilter.SHOW_TEXT),t;
    while((t=w.nextNode())){
      if(used.has(t)||!RX.test(t.nodeValue))continue;
      if(t.parentElement&&t.parentElement.closest('.stcg-price'))continue;
      jobs.push({n:t,h:h});used.add(t);return;
    }
    if(n.hasAttribute('data-element-id'))return;
  }
});
if(!jobs.length)return;
var prices={};
Promise.all(Array.from(new Set(jobs.map(function(j){return j.h}))).map(function(h){
  return fetch('/products/'+h+'.js').then(function(r){return r.ok?r.json():null})
    .then(function(p){if(p)prices[h]=p.price_min!=null?p.price_min:p.price}).catch(function(){})
})).then(function(){
  jobs.forEach(function(j){
    var c=prices[j.h];if(c==null)return;
    if(j.n.nodeType===3)j.n.nodeValue=j.n.nodeValue.replace(RX,fmt(c));
    else j.n.textContent=j.n.textContent.replace(RX,fmt(c));
  })
});
})()</script><script>
(function(){
  var hs=document.querySelectorAll('.st-header');
  for(var i=0;i<hs.length;i++){(function(h){
    var burger=h.querySelector('.st-header__burger');
    if(!burger)return;
    function fit(){
      h.classList.remove('is-nav-collapsed');
      if(h.scrollWidth>h.clientWidth+1)h.classList.add('is-nav-collapsed');
      else h.classList.remove('is-menu-open');
    }
    burger.addEventListener('click',function(e){e.stopPropagation();var open=h.classList.toggle('is-menu-open');burger.setAttribute('aria-expanded',open?'true':'false');});
    var links=h.querySelectorAll('.st-header__nav a');
    for(var k=0;k<links.length;k++)links[k].addEventListener('click',function(){h.classList.remove('is-menu-open');});
    document.addEventListener('click',function(e){if(!h.contains(e.target))h.classList.remove('is-menu-open');});
    var raf;function sched(){if(raf)cancelAnimationFrame(raf);raf=requestAnimationFrame(fit);}
    window.addEventListener('resize',sched);
    if(window.ResizeObserver){try{new ResizeObserver(sched).observe(h);}catch(e){}}
    fit();
  })(hs[i]);}
})();
</script>
<div id="scrollytelling-badge" style="position:fixed;bottom:16px;right:16px;z-index:999999;font-family:'Inter',system-ui,-apple-system,sans-serif;">
  <a href="https://scrollytelling.ai?ref=made_with_scrollytelling" target="_blank" rel="noopener"
     style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px 6px 10px;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border-radius:20px;color:#fff;text-decoration:none;font-size:11px;font-weight:500;letter-spacing:0.2px;opacity:0.75;transition:opacity 0.2s ease,background 0.2s ease;border:1px solid rgba(255,255,255,0.1);box-shadow:0 2px 8px rgba(0,0,0,0.2);"
     onmouseover="this.style.opacity='1';this.style.background='rgba(0,0,0,0.85)'"
     onmouseout="this.style.opacity='0.75';this.style.background='rgba(0,0,0,0.7)'">
    Made with <img src="/assets/silverqueen/sqb-engine-v2/assets/images/logo-symbol-white.png" alt="" style="width:14px;height:14px;" /> Scrollytelling
  </a>
</div><?php if (!empty($loginError)): ?><script>window.addEventListener('load',function(){document.getElementById('auth')?.scrollIntoView({block:'start'});});</script><?php endif; ?></div></div></body>
</html>
