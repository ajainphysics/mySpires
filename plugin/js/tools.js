/**
 * @file Contains library for mySpires-plugin
 * @author Akash Jain
 */

/*=====================================================================*
 * mySpires class
 *=====================================================================*/
/**
 * This class provides an interface to talk with mySpires-API. It contains fundamental functions for the functioning
 * of the plugin.
 */
class mySpires_Plugin {

    static analyze_url(url) {
        let hostname, pathname;
        if(url) {
            hostname = url.split("://")[1].split("/")[0].split("?")[0];
            pathname = url.split(hostname)[1].split("?")[0];
        } else {
            hostname = window.location.hostname;
            pathname = window.location.pathname;
        }

        if(hostname === "arxiv.org") {
            if(pathname.includes("/abs/")) {
                let arxiv = pathname.split("/abs/")[1].split("v")[0];
                return {q: arxiv, field: "arxiv", source: "arxiv_abs"};
            }
            else if(pathname.includes("/pdf/")) {
                let arxiv = pathname.split("/pdf/")[1].split(".pdf")[0];
                return {q: arxiv, field: "arxiv", source: "arxiv_pdf"};
            }
            else if (pathname.split("/")[1] === "search") {
                return {source: "arxiv_search"};
            }
            else if (pathname.split("/")[1] === "list") {
                return {source: "arxiv_list"};
            }
        }

        else if(hostname === "inspirehep.net") {
            if (pathname.includes("/record/")) {
                let inspire = pathname.split("/record/")[1].split("/")[0];
                let subfolder = pathname.split("/record/" + inspire + "/")[1];

                if(!subfolder)
                    return {q: inspire, field: "inspire", source: "inspire_record"};
                else
                    return {q: inspire, field: "inspire", source: "inspire_record_subfolder"};
            }
            else if (pathname.split("/")[1] === "search") {
                return {source: "inspire_search"};
            }
        }

        return false;
    }

    static api(args) {
        return browser.runtime.sendMessage({
            type: "api",
            args: args
        });
    }

    static ping(message) {
        browser.runtime.sendMessage({
            type: "ping",
            message: message
        }).then(console.log).catch(console.log);
    }

    static auth(lenient = false) {
        return browser.runtime.sendMessage({
            type: "auth",
            lenient: lenient
        });
    }
}