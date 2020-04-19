// Return api calls

function api(args) {
    return new Promise((resolve, reject) => {
        mySpires.api(args).then(function (response) {
            resolve(response);
        }).catch(reject);
    });
}

function auth(lenient = false) {
    return new Promise((resolve, reject) => {
        api({
            plugin: 1,
            platform: "webExtensions",
            version: browser.runtime.getManifest().version
        }).then(response => {
            if(["legacy", "outdated"].includes(response.status)) update();

            if(["updated", "legacy"].includes(response.status) || lenient)
                resolve(response);
            else
                reject("mySpires: User or plugin isn't authorized.");
        }).catch(console.log);
    });
}

/**
 * Checks for plugin updates and updates the plugin if available.
 * @return {boolean} - true if updated, false otherwise
 */
function update() {
    if (Date.now() - sessionStorage.getItem("lastUpdateCheck") > 1000 * 60 + 5) {
        browser.runtime.requestUpdateCheck().then(function (status) {
            if (status === "update_available") {
                browser.runtime.reload();
                // reloadInjectedTabs();
            }
            sessionStorage.setItem("lastUpdateCheck", Date.now().toString());
            return true;
        });
    }
    return false;
}

browser.runtime.onMessage.addListener(request => {
    if(request.type === "api") {
        return api(request.args);
    }

    if(request.type === "auth") {
        if(request.lenient) return auth(true);
        else return auth();
    }

    if(request.type === "ping") {
        console.log(request.message);
        return Promise.resolve(true);
    }
});

browser.commands.onCommand.addListener(function(command) {
    if(command.toString() === "toggle-cite-window") {
        console.log('Command:', command);
        ask_page("toggle-cite-window", "1").catch(console.log);
    }
});