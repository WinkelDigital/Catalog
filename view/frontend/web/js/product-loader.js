define(['jquery'], function($){
    'use strict';
    function parseParams(url){
      const params = url.split('?')
      if(params.length > 1){
        return JSON.parse('{"' + decodeURI(params[1].replace(/&/g, "\",\"").replace(/=/g,"\":\"")) + '"}')
      }
      return {}
    }
    return function(config, element){
      let page = 0;
      const loadProduct = function(url, options = {}, cleanFirst = false, pushState = false){
        const resultElWrapper = $(element)
        if(cleanFirst){
          resultElWrapper.empty()
        }
        const loadTrigger = $(config.pager.moreButton)
        loadTrigger.attr('disabled','disabled')
        loadTrigger.text('Loading....')
        resultElWrapper.addClass('loading')
        const data = options
        return new Promise((resolve,reject)=>{
          $.ajax({
            url,
            type:'get',
            data
          }).then(function(result){
            history.pushState({path:this.url,data},'',this.url)
            $('.filter-current').remove()
            $('.filter-content').prepend(result.state)
            loadTrigger.removeAttr('disabled')
            loadTrigger.text(loadTrigger.data('text'))
            resultElWrapper.append(result.html)
            resultElWrapper.removeClass('loading')
            const totitem = result.total
            const page = result.page
            const perpage = result.limit
            const prodToShown = perpage * page
            $('#num-total-products').text(totitem)
            if((perpage * page) >= totitem){
               $('#load-more-prod').hide()
            }
            $('#num-shown-prods').text(prodToShown > totitem?totitem:prodToShown)
            const percentage = prodToShown/totitem * 100
             $('#prod-load-status-val').css('width', (percentage > 100 ? 100:percentage) +'%')
            if(prodToShown >= totitem){
              $(config.pager.moreButton).hide()
            }else{
              $(config.pager.moreButton).show()
            }
            $(config.pager.wrapperEl).show()
            resolve(result)
          })
        })
      }

      var baseUrl = window.location.href
      var curFilter = parseParams(baseUrl)

      $(document).ready(function(){
        loadProduct(baseUrl).then(function(result){
          page = result.page
        })
        window.onpopstate = function() {
          console.log('testttttttt');
        };
      })
      $(config.pager.moreButton).click(function(e){
         e.preventDefault()
         loadProduct(baseUrl,Object.assign(curFilter,{
           'p':page+1
         })).then(function(result){
           page = result.page
         })
      })
      $('.filter-options-content a').click(function(e){
         e.preventDefault()
         curFilter = Object.assign({},
           curFilter,
           parseParams($(this).attr('href')),
           {'p':1}
         );
         loadProduct($(this).attr('href'),curFilter, true, true).then(function(result){
           page = result.page
         })
      })
      $('.filter-content').on('click','.filter-current a', function(e){
        e.preventDefault()
        curFilter = Object.assign(parseParams($(this).attr('href')),{
          'p':1
        })
        loadProduct($(this).attr('href'),curFilter, true, true).then(function(result){
          page = result.page
        })
      })
    }
});
