$("tr").click(function() {
  window.location.href = $(this).find("a").attr("href");});


$(document).ready(function(){
  var section = new Array('.textOptions');
  section = section.join(',');
 
  // Reset Font Size
  var originalFontSize = $(section).css('font-size');
  $(".resetFont").click(function(){
    $(section).css('font-size', originalFontSize);
  });
 
  // Increase Font Size
  $(".increaseFont").click(function(){
    var currentFontSize = $(section).css('font-size');
    var currentFontSizeNum = parseFloat(currentFontSize, 10);
    var newFontSize = currentFontSizeNum*1.1;
    $(section).css('font-size', newFontSize);
    return false;
  });
 
  // Decrease Font Size
  $(".decreaseFont").click(function(){
    var currentFontSize = $(section).css('font-size');
    var currentFontSizeNum = parseFloat(currentFontSize, 10);
    var newFontSize = currentFontSizeNum*0.9;
    $(section).css('font-size', newFontSize);
    return false;
  });
});
// emailto 

        function emailCurrentPage(){
            window.location.href="mailto:?subject="+document.title+"&body="+escape(window.location.href);
        }
//arrow left and right next 

window.addEventListener("scroll",function(){
  var target = document.getElementsByClassName("fadeout");
  if(window.pageYOffset > 300){
   target[0].style.display = "block"; 
  }
else if(window.pageYOffset < 100){
    target[0].style.display = "none";
  }
  if ((window.innerHeight + window.pageYOffset ) >= document.body.offsetHeight) {
   target[0].style.display = "none"; 
    }
  //target[0].innerHTML = 'PageYOffset = ' + window.pageYOffset;
},false);


